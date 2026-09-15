<?php

namespace App\Services\Promotions\DTOs;

/**
 * Class PromotionDiscountBreakdown
 *
 * Immutable Value Object representing the complete cart financial breakdown after promotion evaluation.
 */
final readonly class PromotionDiscountBreakdown
{
    public float $subtotal;

    public float $eligibleSubtotal;

    public float $flashSaleSubtotal;

    public float $itemDiscounts;

    public float $itemDiscountTotal;

    public float $couponDiscount;

    public float $shippingFee;

    public float $shippingDiscount;

    public float $shippingDiscountTotal;

    public float $finalShippingFee;

    public float $totalDiscount;

    public float $finalTotal;

    /** @var array<AppliedRuleDiscount> */
    public array $appliedRules;

    public ?string $couponCode;

    public ?AppliedRuleDiscount $couponDiscountRule;

    public array $freeGifts;

    public array $messages;

    /**
     * @param  float  $subtotal  Total raw cart subtotal before any discounts
     * @param  float  $eligibleSubtotal  / eligible_subtotal Subtotal of non-flash-sale items eligible for promotional discounts
     * @param  float  $flashSaleSubtotal  / flash_sale_subtotal Subtotal of flash sale items
     * @param  float  $itemDiscounts  / item_discounts / itemDiscountTotal / item_discount_total
     * @param  float  $couponDiscount  / coupon_discount
     * @param  float  $shippingFee  / shipping_fee
     * @param  float  $shippingDiscount  / shipping_discount / shippingDiscountTotal / shipping_discount_total
     * @param  float  $finalShippingFee  / final_shipping_fee
     * @param  float  $totalDiscount  / total_discount
     * @param  float  $finalTotal  / final_total
     * @param  array<AppliedRuleDiscount>  $appliedRules  / applied_rules
     * @param  string|null  $couponCode  / coupon_code
     * @param  AppliedRuleDiscount|null  $couponDiscount  / coupon_discount_rule
     * @param  array  $freeGifts  / free_gifts
     * @param  array<string>  $messages
     */
    public function __construct(
        float $subtotal = 0.0,
        float $eligibleSubtotal = 0.0,
        float $flashSaleSubtotal = 0.0,
        float $itemDiscounts = 0.0,
        float $couponDiscount = 0.0,
        float $shippingFee = 0.0,
        float $shippingDiscount = 0.0,
        float $finalShippingFee = 0.0,
        float $totalDiscount = 0.0,
        float $finalTotal = 0.0,
        array $appliedRules = [],
        ?string $couponCode = null,
        ?AppliedRuleDiscount $couponDiscountRule = null,
        array $freeGifts = [],
        array $messages = [],
        // Parameter aliases for camelCase/snake_case flexibility
        ?float $eligible_subtotal = null,
        ?float $flash_sale_subtotal = null,
        ?float $item_discounts = null,
        ?float $item_discount_total = null,
        ?float $itemDiscountTotal = null,
        ?float $coupon_discount = null,
        ?float $shipping_fee = null,
        ?float $shipping_discount = null,
        ?float $shipping_discount_total = null,
        ?float $shippingDiscountTotal = null,
        ?float $final_shipping_fee = null,
        ?float $total_discount = null,
        ?float $final_total = null,
        ?array $applied_rules = null,
        ?string $coupon_code = null,
        ?AppliedRuleDiscount $coupon_discount_rule = null,
        ?array $free_gifts = null
    ) {
        $this->subtotal = $subtotal;
        $this->eligibleSubtotal = $eligible_subtotal ?? $eligibleSubtotal;
        $this->flashSaleSubtotal = $flash_sale_subtotal ?? $flashSaleSubtotal;

        $resolvedItemDiscount = $item_discount_total ?? $itemDiscountTotal ?? $item_discounts ?? $itemDiscounts;
        $this->itemDiscounts = $resolvedItemDiscount;
        $this->itemDiscountTotal = $resolvedItemDiscount;

        $this->couponDiscount = $coupon_discount ?? $couponDiscount;
        $this->shippingFee = $shipping_fee ?? $shippingFee;

        $resolvedShippingDiscount = $shipping_discount_total ?? $shippingDiscountTotal ?? $shipping_discount ?? $shippingDiscount;
        $this->shippingDiscount = $resolvedShippingDiscount;
        $this->shippingDiscountTotal = $resolvedShippingDiscount;

        $this->finalShippingFee = $final_shipping_fee ?? $finalShippingFee;
        $this->totalDiscount = $total_discount ?? $totalDiscount;
        $this->finalTotal = $final_total ?? $finalTotal;
        $this->appliedRules = $applied_rules ?? $appliedRules;
        $this->couponCode = $coupon_code ?? $couponCode;
        $this->couponDiscountRule = $coupon_discount_rule ?? $couponDiscountRule;
        $this->freeGifts = $free_gifts ?? $freeGifts;
        $this->messages = $messages;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'eligible_subtotal' => $this->eligibleSubtotal,
            'flash_sale_subtotal' => $this->flashSaleSubtotal,
            'item_discounts' => $this->itemDiscounts,
            'item_discount_total' => $this->itemDiscountTotal,
            'coupon_discount' => $this->couponDiscount,
            'shipping_fee' => $this->shippingFee,
            'shipping_discount' => $this->shippingDiscount,
            'shipping_discount_total' => $this->shippingDiscountTotal,
            'final_shipping_fee' => $this->finalShippingFee,
            'total_discount' => $this->totalDiscount,
            'final_total' => $this->finalTotal,
            'applied_rules' => $this->appliedRules,
            'coupon_code' => $this->couponCode,
            'coupon_discount_rule' => $this->couponDiscountRule,
            'free_gifts' => $this->freeGifts,
            default => null,
        };
    }

    /**
     * Check if any discount was applied.
     */
    public function hasDiscount(): bool
    {
        return $this->totalDiscount > 0.0 || ! empty($this->freeGifts);
    }

    /**
     * Check if a coupon discount was applied.
     */
    public function hasCouponApplied(): bool
    {
        return $this->couponDiscount > 0.0 || $this->getCouponRule() !== null;
    }

    /**
     * Retrieve the applied coupon rule if any.
     */
    public function getCouponRule(): ?AppliedRuleDiscount
    {
        if ($this->couponDiscountRule !== null) {
            return $this->couponDiscountRule;
        }

        foreach ($this->appliedRules as $rule) {
            if ($rule->isCoupon) {
                return $rule;
            }
        }

        return null;
    }

    public function formattedSubtotal(): string
    {
        return number_format($this->subtotal, 0, ',', '.').'₫';
    }

    public function formattedTotalDiscount(): string
    {
        return number_format($this->totalDiscount, 0, ',', '.').'₫';
    }

    public function getFormattedTotalDiscount(): string
    {
        return $this->formattedTotalDiscount();
    }

    public function formattedFinalShippingFee(): string
    {
        return number_format($this->finalShippingFee, 0, ',', '.').'₫';
    }

    public function formattedFinalTotal(): string
    {
        return number_format($this->finalTotal, 0, ',', '.').'₫';
    }

    public function getFormattedFinalTotal(): string
    {
        return $this->formattedFinalTotal();
    }

    /**
     * Export breakdown to standard array format.
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'eligible_subtotal' => $this->eligibleSubtotal,
            'flash_sale_subtotal' => $this->flashSaleSubtotal,
            'item_discounts' => $this->itemDiscounts,
            'item_discount_total' => $this->itemDiscountTotal,
            'coupon_discount' => $this->couponDiscount,
            'shipping_fee' => $this->shippingFee,
            'shipping_discount' => $this->shippingDiscount,
            'shipping_discount_total' => $this->shippingDiscountTotal,
            'final_shipping_fee' => $this->finalShippingFee,
            'total_discount' => $this->totalDiscount,
            'final_total' => $this->finalTotal,
            'applied_rules' => array_map(fn (AppliedRuleDiscount $r) => $r->toArray(), $this->appliedRules),
            'coupon_code' => $this->couponCode,
            'coupon_discount_rule' => $this->couponDiscountRule?->toArray(),
            'free_gifts' => $this->freeGifts,
            'messages' => $this->messages,
        ];
    }
}
