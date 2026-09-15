<?php

namespace App\Services\Promotions\DTOs;

use App\Models\PromotionRule;

/**
 * Class PromotedPriceResult
 *
 * Immutable Value Object representing the resolved strike-price and promo badge for catalog display.
 */
final readonly class PromotedPriceResult
{
    public float $originalPrice;

    public float $promotedPrice;

    public float $discountPercentage;

    public float $discountPercent;

    public float $discountAmount;

    public string $badgeLabel;

    public int $ruleId;

    public string $ruleName;

    public ?PromotionRule $rule;

    /**
     * @param  float|int  $originalPrice  / original_price
     * @param  float|int  $promotedPrice  / promoted_price
     * @param  float|int  $discountPercentage  / discount_percentage / discountPercent / discount_percent
     * @param  float|null  $discountAmount  / discount_amount / save_amount
     * @param  string  $badgeLabel  / badge_label
     * @param  int  $ruleId  / rule_id
     * @param  string  $ruleName  / rule_name
     */
    public function __construct(
        float|int $originalPrice = 0.0,
        float|int $promotedPrice = 0.0,
        float|int $discountPercentage = 0.0,
        ?float $discountAmount = null,
        string $badgeLabel = '',
        int $ruleId = 0,
        string $ruleName = '',
        ?PromotionRule $rule = null,
        // Aliases
        float|int|null $original_price = null,
        float|int|null $promoted_price = null,
        float|int|null $discountPercent = null,
        float|int|null $discount_percent = null,
        float|int|null $discount_percentage = null,
        ?float $discount_amount = null,
        ?float $save_amount = null,
        ?string $badge_label = null,
        ?int $rule_id = null,
        ?string $rule_name = null
    ) {
        $this->originalPrice = (float) ($original_price ?? $originalPrice);
        $this->promotedPrice = (float) ($promoted_price ?? $promotedPrice);

        $resolvedPercent = (float) ($discount_percentage ?? $discount_percent ?? $discountPercent ?? $discountPercentage);
        $this->discountPercentage = $resolvedPercent;
        $this->discountPercent = $resolvedPercent;

        $resolvedDiscountAmount = $discount_amount ?? $save_amount ?? $discountAmount ?? max(0.0, $this->originalPrice - $this->promotedPrice);
        $this->discountAmount = (float) $resolvedDiscountAmount;

        $this->badgeLabel = $badge_label ?? $badgeLabel;
        $this->ruleId = $rule_id ?? ($rule?->id ?? $ruleId);
        $this->ruleName = $rule_name ?? ($rule?->name ?? $ruleName);
        $this->rule = $rule;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'original_price' => $this->originalPrice,
            'promoted_price' => $this->promotedPrice,
            'discount_percent' => $this->discountPercent,
            'discount_percentage' => $this->discountPercentage,
            'discount_amount' => $this->discountAmount,
            'save_amount' => $this->discountAmount,
            'badge_label' => $this->badgeLabel,
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            default => null,
        };
    }

    /**
     * Calculate absolute savings amount.
     */
    public function getSaveAmount(): float
    {
        return $this->discountAmount;
    }

    public function formattedOriginalPrice(): string
    {
        return number_format($this->originalPrice, 0, ',', '.').'₫';
    }

    public function getFormattedOriginalPrice(): string
    {
        return $this->formattedOriginalPrice();
    }

    public function formattedPromotedPrice(): string
    {
        return number_format($this->promotedPrice, 0, ',', '.').'₫';
    }

    public function getFormattedPromotedPrice(): string
    {
        return $this->formattedPromotedPrice();
    }

    public function formattedDiscountAmount(): string
    {
        return number_format($this->discountAmount, 0, ',', '.').'₫';
    }

    /**
     * Export to standard array format.
     */
    public function toArray(): array
    {
        return [
            'original_price' => $this->originalPrice,
            'promoted_price' => $this->promotedPrice,
            'discount_percentage' => $this->discountPercentage,
            'discount_amount' => $this->discountAmount,
            'badge_label' => $this->badgeLabel,
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'save_amount' => $this->getSaveAmount(),
        ];
    }
}
