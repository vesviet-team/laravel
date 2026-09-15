<?php

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Exceptions\CommerceException;
use App\Exceptions\EmptyCartException;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PromotionRule;
use App\Services\CartService;
use App\Services\GoshipService;
use App\Services\OrderService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessCheckoutAction
{
    public function __construct(
        protected CartService $cartService,
        protected OrderService $orderService,
        protected PromotionEngine $promotionEngine,
        protected GoshipService $goshipService
    ) {}

    /**
     * Execute the full checkout pipeline with pessimistic concurrency locking.
     *
     * 1. Pre-transaction validation and external shipping resolution (Goship API).
     * 2. Atomic DB transaction:
     *    a. Acquire pessimistic row locks (lockForUpdate) on candidate promotion rules.
     *    b. Re-verify global usage limits, per-customer limits, and tier eligibility under lock.
     *    c. Calculate final discounts via PromotionEngine (flash-sale isolation guaranteed).
     *    d. Create Order and OrderItems via OrderService.
     *    e. Deduct inventory stock via InventoryService (with item lockForUpdate).
     *    f. Atomically call PromotionRule::recordUsage() for all applied rules.
     * 3. Clear session cart and dispatch OrderPlaced event post-commit.
     *
     * @param  array  $customerData  Validated checkout request payload.
     * @param  string|null  $couponCode  Optional coupon code from session/request.
     *
     * @throws EmptyCartException|CommerceException|RuntimeException
     */
    public function execute(array $customerData, ?string $couponCode = null): Order
    {
        // Resolve customer identity
        $customerId = $customerData['customer_id'] ?? auth('customer')->id() ?? null;

        // Idempotency lock: guard against rapid double-clicks and concurrent submissions
        $lockKey = $customerId
            ? "checkout:lock:customer:{$customerId}"
            : 'checkout:lock:guest:'.md5(($customerData['phone'] ?? '').':'.($customerData['email'] ?? '').':'.session()->getId());

        $lock = Cache::lock($lockKey, 15);

        if (! $lock->get()) {
            throw new CommerceException('Đơn hàng của bạn đang được xử lý, vui lòng không gửi yêu cầu liên tiếp.');
        }

        try {
            // -------------------------------------------------------------
            // Phase 1: Pre-Transaction Validation & External Network Calls
            // -------------------------------------------------------------
            $cartItems = $this->cartService->getCartItemsDetails();

            if (empty($cartItems)) {
                throw new EmptyCartException('Giỏ hàng của bạn đang trống.');
            }

            $subtotal = (float) $this->cartService->calculateTotal();

            $customer = $customerId ? Customer::find($customerId) : null;
            $customerEmail = trim((string) ($customerData['email'] ?? ($customer?->email ?? '')));

            // Resolve external shipping rates OUTSIDE database transaction
            $shippingFee = (float) $this->resolveShippingFee($customerData, $cartItems);

            $normalizedCouponCode = $couponCode ? strtoupper(trim($couponCode)) : null;

            // -------------------------------------------------------------
            // Phase 2: Atomic DB Transaction with Concurrency Locking
            // -------------------------------------------------------------
            $order = DB::transaction(function () use (
                $customerData,
                $cartItems,
                $subtotal,
                $shippingFee,
                $normalizedCouponCode,
                $customer,
                $customerId,
                $customerEmail
            ) {
                // Step 2.1: Acquire Pessimistic Locks on Candidate Promotion Rules
                $lockedCouponRule = null;
                $legacyCoupon = null;

                if ($normalizedCouponCode) {
                    // Lock candidate promotion rule in DB
                    $lockedCouponRule = PromotionRule::where('code', $normalizedCouponCode)
                        ->where('rule_type', PromotionRule::RULE_TYPE_CART)
                        ->lockForUpdate()
                        ->first();

                    // Backward-compatibility fallback for legacy coupons table
                    if (! $lockedCouponRule && class_exists(Coupon::class)) {
                        $legacyCoupon = Coupon::where('code', $normalizedCouponCode)
                            ->lockForUpdate()
                            ->first();
                    }
                }

                // Lock active automatic cart rules in deterministic order (priority ASC, id ASC)
                $lockedAutomaticRules = PromotionRule::active()
                    ->cartRules()
                    ->automatic()
                    ->orderedByPriority()
                    ->lockForUpdate()
                    ->get();

                // Step 2.2: Re-verify Applicability & Anti-Fraud Under Lock
                $validCouponRule = null;
                if ($lockedCouponRule) {
                    $itemCount = (int) array_sum(array_column($cartItems, 'quantity'));
                    $categoryIds = array_filter(array_column($cartItems, 'category_id'));
                    $productIds = array_filter(array_column($cartItems, 'product_id'));

                    if ($lockedCouponRule->isApplicableToCustomer(
                        customer: $customer,
                        subtotal: $subtotal,
                        itemCount: $itemCount,
                        categoryIds: $categoryIds,
                        email: $customerEmail,
                        productIds: $productIds
                    )) {
                        $validCouponRule = $lockedCouponRule;
                    }
                }

                // Step 2.3: Calculate Authoritative Discounts via PromotionEngine
                $breakdown = $this->promotionEngine->calculateCartDiscounts(
                    subtotal: $subtotal,
                    cartItems: $cartItems,
                    couponCode: $validCouponRule ? $validCouponRule->code : null,
                    shippingFee: $shippingFee,
                    customer: $customer,
                    email: $customerEmail
                );

                $totalDiscount = $breakdown->totalDiscount;

                // Handle legacy coupon calculation if applicable
                if (! $validCouponRule && $legacyCoupon) {
                    $eligibleSubtotal = array_sum(
                        array_map(
                            fn ($item) => empty($item['is_flash_sale'])
                                ? (float) ($item['subtotal'] ?? (($item['price'] ?? 0) * ($item['quantity'] ?? 1)))
                                : 0.0,
                            $cartItems
                        )
                    );

                    $isLegacyApplicable = in_array($legacyCoupon->type, ['free_shipping', 'shipping_discount'])
                        ? $legacyCoupon->isApplicable($subtotal)
                        : ($eligibleSubtotal > 0 && $legacyCoupon->isApplicable($eligibleSubtotal));

                    if ($isLegacyApplicable) {
                        $legacyDiscount = $legacyCoupon->calculateDiscount($eligibleSubtotal, $shippingFee);
                        $totalDiscount += $legacyDiscount;
                    } else {
                        $legacyCoupon = null;
                    }
                }

                // Legacy combo fallback when no automatic PromotionRules are active
                $hasAutoPromotionRules = PromotionRule::query()->cartRules()->automatic()->active()->exists();
                if (! $hasAutoPromotionRules) {
                    $eligibleSubtotal = 0.0;
                    $eligibleItemsCount = 0;
                    foreach ($cartItems as $item) {
                        if (empty($item['is_flash_sale'])) {
                            $eligibleSubtotal += (float) ($item['subtotal'] ?? (($item['price'] ?? 0) * ($item['quantity'] ?? 1)));
                            $eligibleItemsCount += (int) ($item['quantity'] ?? 1);
                        }
                    }
                    if ($eligibleItemsCount >= 2 && $eligibleSubtotal > 0) {
                        $totalDiscount += ($eligibleSubtotal * 0.05);
                    }
                }

                // Guard: total discount cannot exceed total payable amount (subtotal + shippingFee)
                $totalDiscount = min($totalDiscount, $subtotal + $shippingFee);

                // Step 2.4: Create Order & OrderItems, then Deduct Stock (with inventory lockForUpdate)
                $order = $this->orderService->createOrder(
                    customerData: $customerData,
                    cartItems: $cartItems,
                    subtotal: (int) round($subtotal),
                    discountAmount: (int) round($totalDiscount),
                    shippingFee: (int) round($shippingFee)
                );

                // Step 2.5: Atomically Record Usages for All Applied Promotion Rules
                foreach ($breakdown->appliedRules as $applied) {
                    $ruleToRecord = ($validCouponRule && $validCouponRule->id === $applied->ruleId)
                        ? $validCouponRule
                        : $lockedAutomaticRules->firstWhere('id', $applied->ruleId);

                    if (! $ruleToRecord) {
                        $ruleToRecord = PromotionRule::lockForUpdate()->find($applied->ruleId);
                    }

                    if ($ruleToRecord) {
                        $ruleToRecord->recordUsage(
                            customerId: $customerId,
                            orderId: $order->id,
                            email: $customerEmail,
                            discountAmount: (float) $applied->discountAmount
                        );
                    }
                }

                // Increment legacy coupon usage if used
                if ($legacyCoupon instanceof Coupon) {
                    $legacyCoupon->incrementUsage();
                }

                return $order;
            });

            // -------------------------------------------------------------
            // Phase 3: Post-Transaction Finalization
            // -------------------------------------------------------------
            $this->cartService->clear();

            // Initialize payment gateway metadata (e.g. VietQR generation, instructions)
            app(PaymentGatewayManager::class)->process($order);

            // Dispatch domain event post-commit for asynchronous notifications
            OrderPlaced::dispatch($order);

            return $order;
        } finally {
            $lock->release();
        }
    }

    /**
     * Resolve shipping fee via GoshipService outside the DB transaction.
     * Falls back to 0 if API is unreachable to prevent blocking checkout.
     */
    protected function resolveShippingFee(array $customerData, array $cartItems = []): int
    {
        try {
            $totalWeight = 0;
            foreach ($cartItems as $item) {
                $productWeight = (int) ($item['weight'] ?? ($item['product']->weight ?? 500));
                $qty = (int) ($item['quantity'] ?? 1);
                $totalWeight += max(100, $productWeight) * $qty;
            }
            $totalWeight = max(500, $totalWeight);

            $rates = $this->goshipService->getShippingRates(
                [
                    'city' => $customerData['city'] ?? '',
                    'district' => $customerData['district'] ?? '',
                    'ward' => $customerData['ward'] ?? '',
                ],
                ['weight' => $totalWeight]
            );

            return isset($rates[0]['total_amount']) ? (int) $rates[0]['total_amount'] : 0;
        } catch (\Throwable $e) {
            Log::warning('Goship shipping rate resolution failed; falling back to 0', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
