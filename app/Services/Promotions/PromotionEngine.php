<?php

namespace App\Services\Promotions;

use App\Models\Customer;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Observers\PromotionRuleObserver;
use App\Services\Promotions\Contracts\PromotionStrategyInterface;
use App\Services\Promotions\DTOs\PromotedPriceResult;
use App\Services\Promotions\DTOs\PromotionDiscountBreakdown;
use App\Services\Promotions\Strategies\BuyXGetYStrategy;
use App\Services\Promotions\Strategies\FixedAmountStrategy;
use App\Services\Promotions\Strategies\FreeShippingStrategy;
use App\Services\Promotions\Strategies\PercentageWithCapStrategy;
use App\Services\Promotions\Strategies\TieredQuantityStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PromotionEngine
{
    /**
     * Map rule action types to strategy implementation classes.
     *
     * @var array<string, class-string<PromotionStrategyInterface>>
     */
    protected array $strategyMap = [
        PromotionRule::ACTION_PERCENTAGE => PercentageWithCapStrategy::class,
        PromotionRule::ACTION_FIXED_AMOUNT => FixedAmountStrategy::class,
        PromotionRule::ACTION_FREE_SHIPPING => FreeShippingStrategy::class,
        PromotionRule::ACTION_TIERED_QUANTITY => TieredQuantityStrategy::class,
        PromotionRule::ACTION_BUY_X_GET_Y => BuyXGetYStrategy::class,
    ];

    /**
     * Resolve Strategy instance for a given action type.
     */
    public function getStrategy(string $actionType): ?PromotionStrategyInterface
    {
        $class = $this->strategyMap[$actionType] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * In-memory cached active catalog rules (TTL: 3600 seconds = 1 hour).
     * Automatically invalidated by PromotionRuleObserver on rule mutation.
     *
     * @return Collection<int, PromotionRule>
     */
    public function getActiveCatalogRules(): Collection
    {
        class_exists(PromotionRule::class);
        class_exists(\Illuminate\Database\Eloquent\Collection::class);

        $rules = Cache::remember(
            PromotionRuleObserver::CATALOG_RULES_CACHE_KEY,
            3600,
            fn () => PromotionRule::query()
                ->catalogRules()
                ->active()
                ->orderedByPriority()
                ->get()
        );

        if (! $rules instanceof Collection || ($rules->isNotEmpty() && $rules->first() instanceof \__PHP_Incomplete_Class)) {
            Cache::forget(PromotionRuleObserver::CATALOG_RULES_CACHE_KEY);
            $rules = PromotionRule::query()
                ->catalogRules()
                ->active()
                ->orderedByPriority()
                ->get();
        }

        return $rules;
    }

    /**
     * Resolve Catalog Promoted Strike Price and Promo Badge for a Product.
     */
    public function resolveProductPromotedPrice(Product $product): ?PromotedPriceResult
    {
        $originalPrice = (float) $product->price;
        if ($originalPrice <= 0) {
            return null;
        }

        $rules = $this->getActiveCatalogRules();
        if ($rules->isEmpty()) {
            return null;
        }

        $categoryId = (int) ($product->category_id ?? 0);
        $productId = (int) $product->id;

        foreach ($rules as $rule) {
            $conditions = $rule->conditions ?? [];

            // Match Category condition if present
            if (! empty($conditions['category_ids']) && is_array($conditions['category_ids'])) {
                $categoryIds = array_map('intval', $conditions['category_ids']);
                if (! in_array($categoryId, $categoryIds, true)) {
                    continue;
                }
            }

            // Match Product ID condition if present
            if (! empty($conditions['product_ids']) && is_array($conditions['product_ids'])) {
                $productIds = array_map('intval', $conditions['product_ids']);
                if (! in_array($productId, $productIds, true)) {
                    continue;
                }
            }

            // Calculate discount for matched catalog rule
            $discountAmount = 0.0;
            $discountPercentage = 0.0;
            $badgeLabel = '';

            if ($rule->action_type === PromotionRule::ACTION_PERCENTAGE) {
                $discountPercentage = min(100.0, max(0.0, (float) $rule->discount_value));
                $rawDiscount = $originalPrice * ($discountPercentage / 100.0);

                if ($rule->max_discount_amount !== null && $rule->max_discount_amount > 0) {
                    $discountAmount = min($rawDiscount, (float) $rule->max_discount_amount);
                } else {
                    $discountAmount = $rawDiscount;
                }

                $badgeLabel = "-{$discountPercentage}% PROMO";
            } elseif ($rule->action_type === PromotionRule::ACTION_FIXED_AMOUNT) {
                $discountAmount = min((float) $rule->discount_value, $originalPrice);
                $discountPercentage = $originalPrice > 0 ? round(($discountAmount / $originalPrice) * 100, 1) : 0.0;
                $badgeLabel = '-'.number_format($discountAmount, 0, ',', '.').'₫ PROMO';
            } else {
                continue;
            }

            $promotedPrice = max(0.0, round($originalPrice - $discountAmount, 2));

            return new PromotedPriceResult(
                originalPrice: $originalPrice,
                promotedPrice: $promotedPrice,
                discountPercentage: $discountPercentage,
                discountAmount: $discountAmount,
                badgeLabel: $badgeLabel,
                ruleId: (int) $rule->id,
                ruleName: (string) $rule->name,
                rule: $rule
            );
        }

        return null;
    }

    /**
     * Execute full Cart Discount Calculation Pipeline.
     *
     * @param  float  $subtotal  Raw cart subtotal before any discounts
     * @param  array  $cartItems  Enriched cart items from CartService
     * @param  string|null  $couponCode  Optional promo/coupon code
     * @param  float  $shippingFee  Shipping fee in VND
     * @param  Customer|null  $customer  Authenticated customer model
     * @param  string  $email  Customer/guest email for tier and anti-fraud verification
     */
    public function calculateCartDiscounts(
        float $subtotal,
        array $cartItems,
        ?string $couponCode = null,
        float $shippingFee = 0.0,
        ?Customer $customer = null,
        string $email = ''
    ): PromotionDiscountBreakdown {
        // 1. Partition cart into eligible regular items and flash-sale items
        $eligibleCartItems = [];
        $flashSaleCartItems = [];
        $eligibleSubtotal = 0.0;
        $flashSaleSubtotal = 0.0;
        $eligibleItemCount = 0;
        $categoryIds = [];
        $productIds = [];

        foreach ($cartItems as $item) {
            $itemPrice = (float) ($item['price'] ?? 0.0);
            $itemQty = (int) ($item['quantity'] ?? 1);
            $lineTotal = (float) ($item['subtotal'] ?? ($itemPrice * $itemQty));
            $pid = (int) ($item['product_id'] ?? $item['id'] ?? 0);
            $cid = (int) ($item['category_id'] ?? 0);

            if ($pid > 0) {
                $productIds[] = $pid;
            }
            if ($cid > 0) {
                $categoryIds[] = $cid;
            }

            if (empty($item['is_flash_sale'])) {
                $eligibleCartItems[] = $item;
                $eligibleSubtotal += $lineTotal;
                $eligibleItemCount += $itemQty;
            } else {
                $flashSaleCartItems[] = $item;
                $flashSaleSubtotal += $lineTotal;
            }
        }

        $productIds = array_values(array_unique($productIds));
        $categoryIds = array_values(array_unique($categoryIds));

        $appliedRules = [];
        $runningItemDiscount = 0.0;
        $runningShippingDiscount = 0.0;
        $remainingEligibleSubtotal = $eligibleSubtotal;
        $freeGifts = [];
        $messages = [];

        // 2. Evaluate Automatic Cart Rules (Sorted priority ASC: 0 = highest priority)
        $automaticRules = PromotionRule::query()
            ->cartRules()
            ->automatic()
            ->active()
            ->orderedByPriority()
            ->get();

        foreach ($automaticRules as $rule) {
            // Check applicability against customer & cart conditions
            if (! $rule->isApplicableToCustomer($customer, $eligibleSubtotal, $eligibleItemCount, $categoryIds, $email, $productIds)) {
                continue;
            }

            $strategy = $this->getStrategy($rule->action_type);
            if (! $strategy) {
                continue;
            }

            $discountDto = $strategy->calculate(
                $rule,
                $remainingEligibleSubtotal,
                $cartItems,
                max(0.0, $shippingFee - $runningShippingDiscount)
            );

            if ($discountDto !== null && ($discountDto->discountAmount > 0 || ! empty($discountDto->freeGift))) {
                $appliedRules[] = $discountDto;

                if ($discountDto->isShippingDiscount()) {
                    $runningShippingDiscount += $discountDto->discountAmount;
                    $runningShippingDiscount = min($runningShippingDiscount, $shippingFee);
                } else {
                    $runningItemDiscount += $discountDto->discountAmount;
                    $runningItemDiscount = min($runningItemDiscount, $eligibleSubtotal);
                    $remainingEligibleSubtotal = max(0.0, $eligibleSubtotal - $runningItemDiscount);
                }

                if (! empty($discountDto->freeGift)) {
                    $freeGifts[] = $discountDto->freeGift;
                }

                // Stop Further Rules short-circuit
                if ($rule->stop_further_rules) {
                    $messages[] = "Đã áp dụng ưu tiên: {$rule->name}";
                    break;
                }
            }
        }

        // 3. Evaluate Coupon Code if provided
        $couponDiscountDto = null;
        $cleanCouponCode = $couponCode ? strtoupper(trim($couponCode)) : null;

        if ($cleanCouponCode) {
            $couponRule = PromotionRule::query()
                ->byCode($cleanCouponCode)
                ->cartRules()
                ->active()
                ->first();

            if ($couponRule) {
                // Free shipping coupon checks against full subtotal; percentage/fixed checks eligibleSubtotal
                $checkSubtotal = ($couponRule->action_type === PromotionRule::ACTION_FREE_SHIPPING)
                    ? $subtotal
                    : $eligibleSubtotal;

                if ($couponRule->isApplicableToCustomer($customer, $checkSubtotal, $eligibleItemCount, $categoryIds, $email, $productIds)) {
                    $strategy = $this->getStrategy($couponRule->action_type);
                    if ($strategy) {
                        $couponDiscountDto = $strategy->calculate(
                            $couponRule,
                            $remainingEligibleSubtotal,
                            $cartItems,
                            max(0.0, $shippingFee - $runningShippingDiscount)
                        );

                        if ($couponDiscountDto !== null && ($couponDiscountDto->discountAmount > 0 || ! empty($couponDiscountDto->freeGift))) {
                            $appliedRules[] = $couponDiscountDto;

                            if ($couponDiscountDto->isShippingDiscount()) {
                                $runningShippingDiscount += $couponDiscountDto->discountAmount;
                                $runningShippingDiscount = min($runningShippingDiscount, $shippingFee);
                            } else {
                                $runningItemDiscount += $couponDiscountDto->discountAmount;
                                $runningItemDiscount = min($runningItemDiscount, $eligibleSubtotal);
                            }

                            if (! empty($couponDiscountDto->freeGift)) {
                                $freeGifts[] = $couponDiscountDto->freeGift;
                            }
                        }
                    }
                } else {
                    $messages[] = "Mã ưu đãi [{$cleanCouponCode}] không đủ điều kiện áp dụng cho đơn hàng.";
                }
            } else {
                $messages[] = "Mã ưu đãi [{$cleanCouponCode}] không hợp lệ hoặc đã hết hạn.";
            }
        }

        // 4. Compile Final Financial Totals
        $finalShippingFee = max(0.0, $shippingFee - $runningShippingDiscount);
        $itemDiscountTotal = min($runningItemDiscount, $eligibleSubtotal);
        $shippingDiscountTotal = min($runningShippingDiscount, $shippingFee);
        $totalDiscount = $itemDiscountTotal + $shippingDiscountTotal;
        $finalTotal = max(0.0, ($subtotal - $itemDiscountTotal) + $finalShippingFee);

        return new PromotionDiscountBreakdown(
            subtotal: $subtotal,
            eligibleSubtotal: $eligibleSubtotal,
            flashSaleSubtotal: $flashSaleSubtotal,
            itemDiscounts: $itemDiscountTotal,
            couponDiscount: $couponDiscountDto ? $couponDiscountDto->discountAmount : 0.0,
            shippingFee: $shippingFee,
            shippingDiscount: $shippingDiscountTotal,
            finalShippingFee: $finalShippingFee,
            totalDiscount: $totalDiscount,
            finalTotal: $finalTotal,
            appliedRules: $appliedRules,
            couponCode: $cleanCouponCode,
            couponDiscountRule: $couponDiscountDto,
            freeGifts: $freeGifts,
            messages: $messages
        );
    }
}
