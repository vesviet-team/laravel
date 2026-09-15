<?php

namespace App\Livewire;

use App\Models\PromotionRule;
use App\Services\CartService;
use App\Services\PromotionEngine;
use App\Services\Promotions\DTOs\PromotionDiscountBreakdown;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CartDrawer extends Component
{
    public bool $isOpen = false;

    /**
     * [I-01] PromotionEngine injected via mount() — not stored as Livewire public property
     * (Livewire serializes public properties; service objects must be protected/private).
     */
    protected PromotionEngine $promotionEngine;

    /** @var array Enriched cart items from CartService */
    public array $cartItems = [];

    /** @var string Manual coupon input model */
    public string $couponCode = '';

    /** @var string|null Active coupon code stored in session */
    public ?string $appliedCouponCode = null;

    /** @var string|null Feedback error message */
    public ?string $couponError = null;

    /** @var string|null Feedback success message */
    public ?string $couponSuccess = null;

    /** @var bool Toggle state for available coupons tray */
    public bool $isCouponsTrayOpen = false;

    /**
     * Estimated shipping fee for cart promotional calculations (VND).
     */
    public float $estimatedShippingFee = 30000.0;

    public function boot(PromotionEngine $promotionEngine): void
    {
        $this->promotionEngine = $promotionEngine;
    }

    public function mount(CartService $cartService): void
    {
        $this->loadCart($cartService);

        $sessionCoupon = session()->get('coupon');
        if ($sessionCoupon) {
            $this->appliedCouponCode = strtoupper(trim($sessionCoupon));
            $this->couponCode = $this->appliedCouponCode;
        }
    }

    #[On('cart-updated')]
    public function loadCart(CartService $cartService): void
    {
        $rawItems = $cartService->getCartItemsDetails();
        $this->cartItems = array_map(function ($item) {
            if (isset($item['promoted_result']) && is_object($item['promoted_result']) && method_exists($item['promoted_result'], 'toArray')) {
                $item['promoted_result'] = $item['promoted_result']->toArray();
            }

            return $item;
        }, $rawItems);
    }

    #[On('cart-cleared')]
    public function onCartCleared(): void
    {
        $this->cartItems = [];
        $this->appliedCouponCode = null;
        $this->couponCode = '';
        $this->couponError = null;
        $this->couponSuccess = null;
        session()->forget('coupon');
    }

    #[On('open-cart')]
    public function openCart(): void
    {
        $this->isOpen = true;
    }

    public function closeCart(): void
    {
        $this->isOpen = false;
    }

    public function toggleCouponsTray(): void
    {
        $this->isCouponsTrayOpen = ! $this->isCouponsTrayOpen;
    }

    public function updateQuantity(int $productId, ?int $variantId, int $qty, CartService $cartService): void
    {
        if ($qty < 1) {
            return;
        }

        $cartService->update($productId, $variantId, $qty);
        $this->loadCart($cartService);
        $this->dispatch('cart-updated');
    }

    public function removeItem(int $productId, ?int $variantId, CartService $cartService): void
    {
        $cartService->remove($productId, $variantId);
        $this->loadCart($cartService);
        $this->dispatch('cart-updated');
        $this->dispatch('toast', message: 'Đã xoá sản phẩm khỏi giỏ hàng.', type: 'info');
    }

    public function clearCart(CartService $cartService): void
    {
        $cartService->clear();
        $this->onCartCleared();
        $this->dispatch('cart-updated');
        $this->dispatch('toast', message: 'Đã làm trống giỏ hàng.', type: 'info');
    }

    /**
     * Apply coupon code (from manual input or 1-Click tray button).
     */
    public function applyCoupon(
        ?string $code = null,
        ?CartService $cartService = null
    ): void {
        // [I-01] Livewire v3 resolves CartService via method injection when called from wire:click.
        // The nullable fallback is kept for programmatic test calls only.
        $cartService = $cartService ?? app(CartService::class); // acceptable: FormRequest-style context

        $this->couponError = null;
        $this->couponSuccess = null;

        $targetCode = strtoupper(trim($code ?? $this->couponCode));

        if (empty($targetCode)) {
            $this->couponError = 'Vui lòng nhập mã giảm giá.';

            return;
        }

        if (empty($this->cartItems)) {
            $this->couponError = 'Giỏ hàng đang trống, không thể áp dụng mã.';

            return;
        }

        // Look up candidate coupon rule
        $couponRule = PromotionRule::query()
            ->active()
            ->cartRules()
            ->byCode($targetCode)
            ->first();

        if (! $couponRule) {
            $this->couponError = "Mã giảm giá [{$targetCode}] không tồn tại hoặc đã hết hạn.";
            $this->dispatch('toast', message: $this->couponError, type: 'error');

            return;
        }

        $customer = auth('customer')->user();
        $customerEmail = $customer?->email ?? '';
        $categoryIds = array_values(array_unique(array_filter(array_column($this->cartItems, 'category_id'))));
        $productIds = array_values(array_unique(array_filter(array_column($this->cartItems, 'product_id'))));

        $eligibleSubtotal = (float) collect($this->cartItems)
            ->filter(fn ($item) => empty($item['is_flash_sale']))
            ->sum(fn ($item) => (float) ($item['subtotal'] ?? ($item['price'] * $item['quantity'])));

        $checkSubtotal = ($couponRule->action_type === PromotionRule::ACTION_FREE_SHIPPING)
            ? $this->subtotal
            : $eligibleSubtotal;

        if (! $couponRule->isApplicableToCustomer(
            customer: $customer,
            subtotal: $checkSubtotal,
            itemCount: $this->totalQuantity,
            categoryIds: $categoryIds,
            email: $customerEmail,
            productIds: $productIds
        )) {
            if ($couponRule->min_order_amount > 0 && $checkSubtotal < (float) $couponRule->min_order_amount) {
                $gap = (float) $couponRule->min_order_amount - $checkSubtotal;
                $this->couponError = "Mã [{$targetCode}] yêu cầu đơn tối thiểu ".number_format($couponRule->min_order_amount, 0, ',', '.').'₫ (Cần thêm '.number_format($gap, 0, ',', '.').'₫).';
            } elseif ($couponRule->min_quantity > 0 && $this->totalQuantity < $couponRule->min_quantity) {
                $gapQty = $couponRule->min_quantity - $this->totalQuantity;
                $this->couponError = "Mã [{$targetCode}] yêu cầu tối thiểu {$couponRule->min_quantity} sản phẩm (Cần thêm {$gapQty} sản phẩm).";
            } else {
                $this->couponError = "Mã giảm giá [{$targetCode}] không đủ điều kiện áp dụng cho đơn hàng hiện tại.";
            }

            $this->dispatch('toast', message: $this->couponError, type: 'error');

            return;
        }

        // Verify discount outcome via PromotionEngine — use injected instance
        $breakdown = $this->promotionEngine->calculateCartDiscounts(
            subtotal: $this->subtotal,
            cartItems: $this->cartItems,
            couponCode: $targetCode,
            shippingFee: $this->estimatedShippingFee,
            customer: $customer,
            email: $customerEmail
        );

        if (! $breakdown->hasCouponApplied() && ! $breakdown->hasDiscount()) {
            $this->couponError = "Mã giảm giá [{$targetCode}] không tạo ra chiết khấu khả dụng.";
            $this->dispatch('toast', message: $this->couponError, type: 'error');

            return;
        }

        // Successfully applied
        session()->put('coupon', $targetCode);
        $this->appliedCouponCode = $targetCode;
        $this->couponCode = $targetCode;
        $this->couponSuccess = "Đã áp dụng mã [{$targetCode}] thành công!";
        $this->dispatch('coupon-applied', discount: $breakdown->couponDiscount);
        $this->dispatch('toast', message: $this->couponSuccess, type: 'success');
    }

    /**
     * Remove currently applied coupon.
     */
    public function removeCoupon(): void
    {
        $code = $this->appliedCouponCode;
        session()->forget('coupon');
        $this->appliedCouponCode = null;
        $this->couponCode = '';
        $this->couponError = null;
        $this->couponSuccess = null;

        $this->dispatch('coupon-removed');
        $this->dispatch('toast', message: 'Đã gỡ mã giảm giá'.($code ? " [{$code}]" : '').'.', type: 'info');
    }

    #[Computed]
    public function subtotal(): float
    {
        return (float) array_sum(array_column($this->cartItems, 'subtotal'));
    }

    #[Computed]
    public function totalQuantity(): int
    {
        return (int) array_sum(array_column($this->cartItems, 'quantity'));
    }

    #[Computed]
    public function breakdown(): PromotionDiscountBreakdown
    {
        // [I-01] Use injected engine property — no app() service locator
        $customer = auth('customer')->user();
        $email = $customer?->email ?? '';

        return $this->promotionEngine->calculateCartDiscounts(
            subtotal: $this->subtotal,
            cartItems: $this->cartItems,
            couponCode: $this->appliedCouponCode,
            shippingFee: $this->estimatedShippingFee,
            customer: $customer,
            email: $email
        );
    }

    #[Computed]
    public function netTotal(): float
    {
        return $this->breakdown->finalTotal;
    }

    #[Computed]
    public function totalDiscount(): float
    {
        return $this->breakdown->totalDiscount;
    }

    /**
     * Calculate dynamic smart motivational nudge (Free Shipping / Tiered Upgrade).
     */
    #[Computed]
    public function smartNudge(): ?array
    {
        if (empty($this->cartItems)) {
            return null;
        }

        $subtotal = $this->subtotal;
        $totalQty = $this->totalQuantity;

        // 1. Evaluate Free Shipping Nudge
        $freeShippingRule = PromotionRule::query()
            ->active()
            ->cartRules()
            ->where('action_type', PromotionRule::ACTION_FREE_SHIPPING)
            ->orderedByPriority()
            ->first();

        if ($freeShippingRule) {
            $threshold = (float) $freeShippingRule->min_order_amount;
            if ($threshold > 0) {
                if ($subtotal >= $threshold) {
                    // Check if Tiered Quantity upgrade is also available for further incentive
                    $tieredNudge = $this->resolveTieredNudge($totalQty);
                    if ($tieredNudge && ! $tieredNudge['is_completed']) {
                        return $tieredNudge;
                    }

                    return [
                        'type' => 'free_shipping',
                        'title' => 'Miễn Phí Vận Chuyển',
                        'message' => '🎉 Tuyệt vời! Đơn hàng của bạn đã đủ điều kiện Freeship toàn quốc!',
                        'progress_percent' => 100.0,
                        'gap_amount' => 0.0,
                        'gap_quantity' => 0,
                        'target_amount' => $threshold,
                        'is_completed' => true,
                        'icon' => 'truck',
                        'badge' => 'FREESHIP ĐẠT 100%',
                    ];
                }

                $gap = $threshold - $subtotal;
                $progress = min(100.0, max(0.0, round(($subtotal / $threshold) * 100, 1)));

                return [
                    'type' => 'free_shipping',
                    'title' => 'Ưu Đãi Vận Chuyển',
                    'message' => 'Mua thêm '.number_format($gap, 0, ',', '.').'₫ để nhận FREESHIP toàn quốc!',
                    'progress_percent' => $progress,
                    'gap_amount' => $gap,
                    'gap_quantity' => 0,
                    'target_amount' => $threshold,
                    'is_completed' => false,
                    'icon' => 'truck',
                    'badge' => 'CÒN THIẾU '.number_format($gap, 0, ',', '.').'₫',
                ];
            }
        }

        // 2. Evaluate Tiered Quantity Nudge
        $tieredNudge = $this->resolveTieredNudge($totalQty);
        if ($tieredNudge) {
            return $tieredNudge;
        }

        return null;
    }

    /**
     * Resolve Tiered Quantity Nudge steps.
     */
    protected function resolveTieredNudge(int $currentQty): ?array
    {
        $tieredRule = PromotionRule::query()
            ->active()
            ->cartRules()
            ->where('action_type', PromotionRule::ACTION_TIERED_QUANTITY)
            ->orderedByPriority()
            ->first();

        if (! $tieredRule) {
            return null;
        }

        $conditions = $tieredRule->conditions ?? [];
        $steps = $conditions['tiered_steps'] ?? $conditions['tiers'] ?? [];

        if (empty($steps) || ! is_array($steps)) {
            if ($tieredRule->min_quantity > 0) {
                $steps = [
                    ['min_qty' => $tieredRule->min_quantity, 'discount_percent' => (float) $tieredRule->discount_value],
                ];
            }
        }

        if (empty($steps)) {
            return null;
        }

        // Sort ascending by min_qty
        usort($steps, function ($a, $b) {
            $qtyA = (int) ($a['min_qty'] ?? $a['qty'] ?? 0);
            $qtyB = (int) ($b['min_qty'] ?? $b['qty'] ?? 0);

            return $qtyA <=> $qtyB;
        });

        // Find the next step not yet reached
        foreach ($steps as $step) {
            $minQty = (int) ($step['min_qty'] ?? $step['qty'] ?? 0);
            $percent = (float) ($step['discount_percent'] ?? $step['percent'] ?? $step['value'] ?? 0);

            if ($currentQty < $minQty && $minQty > 0) {
                $gapQty = $minQty - $currentQty;
                $progress = min(100.0, max(0.0, round(($currentQty / $minQty) * 100, 1)));

                return [
                    'type' => 'tiered_quantity',
                    'title' => 'Chiết Khấu Số Lượng',
                    'message' => "Thêm {$gapQty} sản phẩm nữa để được GIẢM {$percent}% toàn đơn!",
                    'progress_percent' => $progress,
                    'gap_amount' => 0.0,
                    'gap_quantity' => $gapQty,
                    'target_amount' => 0.0,
                    'is_completed' => false,
                    'icon' => 'sparkles',
                    'badge' => "THÊM {$gapQty} SP → GIẢM {$percent}%",
                ];
            }
        }

        // At max tier
        $lastStep = end($steps);
        $maxPercent = (float) ($lastStep['discount_percent'] ?? $lastStep['percent'] ?? $tieredRule->discount_value);

        return [
            'type' => 'tiered_quantity',
            'title' => 'Chiết Khấu Tối Đa',
            'message' => "🎉 Bạn đang nhận mức chiết khấu số lượng cao nhất ({$maxPercent}%)!",
            'progress_percent' => 100.0,
            'gap_amount' => 0.0,
            'gap_quantity' => 0,
            'target_amount' => 0.0,
            'is_completed' => true,
            'icon' => 'sparkles',
            'badge' => "ĐẠT MỨC GIẢM {$maxPercent}%",
        ];
    }

    /**
     * Query available active Cart Sales Rules with coupon codes for 1-Click Tray.
     */
    #[Computed]
    public function availableCoupons(): Collection
    {
        $rules = PromotionRule::query()
            ->active()
            ->cartRules()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderedByPriority()
            ->get();

        $customer = auth('customer')->user();
        $email = $customer?->email ?? '';
        $subtotal = $this->subtotal;
        $totalQty = $this->totalQuantity;
        $categoryIds = array_values(array_unique(array_filter(array_column($this->cartItems, 'category_id'))));
        $productIds = array_values(array_unique(array_filter(array_column($this->cartItems, 'product_id'))));

        return $rules->map(function (PromotionRule $rule) use ($customer, $subtotal, $totalQty, $categoryIds, $email, $productIds) {
            $isApplied = ($this->appliedCouponCode === $rule->code);
            $isEligible = $rule->isApplicableToCustomer(
                customer: $customer,
                subtotal: $subtotal,
                itemCount: $totalQty,
                categoryIds: $categoryIds,
                email: $email,
                productIds: $productIds
            );

            $ineligibleReason = null;
            if (! $isEligible) {
                if ($rule->min_order_amount > 0 && $subtotal < (float) $rule->min_order_amount) {
                    $gap = (float) $rule->min_order_amount - $subtotal;
                    $ineligibleReason = 'Mua thêm '.number_format($gap, 0, ',', '.').'₫';
                } elseif ($rule->min_quantity > 0 && $totalQty < $rule->min_quantity) {
                    $gapQty = $rule->min_quantity - $totalQty;
                    $ineligibleReason = "Thêm {$gapQty} sản phẩm";
                } elseif ($rule->target_customer_tier !== PromotionRule::TIER_ALL && $customer === null) {
                    $ineligibleReason = 'Cần đăng nhập';
                } else {
                    $ineligibleReason = 'Chưa đủ điều kiện';
                }
            }

            return (object) [
                'id' => $rule->id,
                'name' => $rule->name,
                'code' => $rule->code,
                'action_type' => $rule->action_type,
                'discount_value' => (float) $rule->discount_value,
                'formatted_discount' => $rule->formatted_discount,
                'min_order_amount' => (float) $rule->min_order_amount,
                'min_order_formatted' => $rule->min_order_amount > 0 ? 'Đơn tối thiểu '.number_format($rule->min_order_amount, 0, ',', '.').'₫' : 'Mọi đơn hàng',
                'description' => $rule->conditions['description'] ?? $rule->name,
                'is_applied' => $isApplied,
                'is_eligible' => $isEligible,
                'ineligible_reason' => $ineligibleReason,
                'ends_at' => $rule->ends_at ? $rule->ends_at->format('d/m/Y') : null,
            ];
        });
    }

    public function render()
    {
        return view('livewire.cart-drawer');
    }
}
