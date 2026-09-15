<?php

namespace App\Services\Promotions\Contracts;

use App\Models\PromotionRule;
use App\Services\Promotions\DTOs\AppliedRuleDiscount;

/**
 * Interface PromotionStrategyInterface
 *
 * Defines the contract for all promotion calculation strategies.
 * Strategies must be pure, stateless services that compute monetary discounts
 * based strictly on the provided rule, eligible subtotal, cart items, and shipping fee.
 */
interface PromotionStrategyInterface
{
    /**
     * Calculate the discount amount produced by this rule against the current cart/context.
     *
     * @param  PromotionRule  $rule  The rule being evaluated
     * @param  float  $eligibleSubtotal  Subtotal of items eligible for discount (excluding flash-sale items)
     * @param  array  $cartItems  Enriched cart item array from CartService
     * @param  float  $shippingFee  Current shipping fee in VND
     * @return AppliedRuleDiscount|null Returns AppliedRuleDiscount if discount > 0, null otherwise
     */
    public function calculate(
        PromotionRule $rule,
        float $eligibleSubtotal,
        array $cartItems,
        float $shippingFee = 0.0
    ): ?AppliedRuleDiscount;
}
