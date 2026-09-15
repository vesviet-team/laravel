<?php

namespace App\Services\Promotions\DTOs;

use App\Models\PromotionRule;

/**
 * Class AppliedRuleDiscount
 *
 * Immutable Value Object representing a single successfully applied promotion rule.
 */
final readonly class AppliedRuleDiscount
{
    public int $ruleId;

    public string $ruleName;

    public ?string $ruleCode;

    public string $actionType;

    public float $discountAmount;

    public string $target;

    public string $description;

    public bool $isCoupon;

    public ?array $freeGift;

    public ?PromotionRule $rule;

    /**
     * @param  int  $ruleId  / rule_id
     * @param  string  $ruleName  / rule_name
     * @param  string|null  $ruleCode  / rule_code
     * @param  string  $actionType  / action_type
     * @param  float  $discountAmount  / discount_amount
     * @param  string  $target  ('item' | 'shipping' | 'cart')
     * @param  bool  $isCoupon  / is_coupon
     * @param  array|null  $freeGift  / free_gift
     */
    public function __construct(
        int $ruleId = 0,
        string $ruleName = '',
        ?string $ruleCode = null,
        string $actionType = '',
        float $discountAmount = 0.0,
        string $target = 'item',
        string $description = '',
        bool $isCoupon = false,
        ?array $freeGift = null,
        ?PromotionRule $rule = null,
        // Named parameter aliases for snake_case compatibility
        ?int $rule_id = null,
        ?string $rule_name = null,
        ?string $rule_code = null,
        ?string $action_type = null,
        ?float $discount_amount = null,
        ?bool $is_coupon = null,
        ?array $free_gift = null
    ) {
        $this->ruleId = $rule_id ?? $ruleId;
        $this->ruleName = $rule_name ?? $ruleName;
        $this->ruleCode = $rule_code ?? $ruleCode;
        $this->actionType = $action_type ?? $actionType;
        $this->discountAmount = $discount_amount ?? $discountAmount;
        $this->target = $target;
        $this->description = $description;
        $this->isCoupon = $is_coupon ?? $isCoupon;
        $this->freeGift = $free_gift ?? $freeGift;
        $this->rule = $rule;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'rule_code' => $this->ruleCode,
            'action_type' => $this->actionType,
            'discount_amount' => $this->discountAmount,
            'is_coupon' => $this->isCoupon,
            'free_gift' => $this->freeGift,
            default => null,
        };
    }

    public function isShippingDiscount(): bool
    {
        return $this->target === 'shipping' || $this->actionType === PromotionRule::ACTION_FREE_SHIPPING;
    }

    public function isItemDiscount(): bool
    {
        return $this->target === 'item' || $this->target === 'cart' || ! $this->isShippingDiscount();
    }

    public function formattedDiscountAmount(): string
    {
        return number_format($this->discountAmount, 0, ',', '.').'₫';
    }

    public function getFormattedDiscountAttribute(): string
    {
        return $this->formattedDiscountAmount();
    }

    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'rule_code' => $this->ruleCode,
            'action_type' => $this->actionType,
            'discount_amount' => $this->discountAmount,
            'target' => $this->target,
            'description' => $this->description,
            'is_coupon' => $this->isCoupon,
            'free_gift' => $this->freeGift,
        ];
    }
}
