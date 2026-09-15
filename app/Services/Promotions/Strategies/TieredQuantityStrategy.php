<?php

namespace App\Services\Promotions\Strategies;

use App\Models\PromotionRule;
use App\Services\Promotions\Contracts\PromotionStrategyInterface;
use App\Services\Promotions\DTOs\AppliedRuleDiscount;

class TieredQuantityStrategy implements PromotionStrategyInterface
{
    public function calculate(
        PromotionRule $rule,
        float $eligibleSubtotal,
        array $cartItems,
        float $shippingFee = 0.0
    ): ?AppliedRuleDiscount {
        if ($eligibleSubtotal <= 0 || empty($cartItems)) {
            return null;
        }

        $conditions = $rule->conditions ?? [];
        $targetCategoryIds = ! empty($conditions['category_ids']) ? array_map('intval', (array) $conditions['category_ids']) : [];
        $targetProductIds = ! empty($conditions['product_ids']) ? array_map('intval', (array) $conditions['product_ids']) : [];

        $matchingQty = 0;
        $matchingSubtotal = 0.0;

        foreach ($cartItems as $item) {
            if (! empty($item['is_flash_sale'])) {
                continue;
            }

            $pid = (int) ($item['product_id'] ?? $item['id'] ?? 0);
            $catId = (int) ($item['category_id'] ?? 0);

            if (! empty($targetProductIds) && ! in_array($pid, $targetProductIds, true)) {
                continue;
            }
            if (! empty($targetCategoryIds) && ! in_array($catId, $targetCategoryIds, true)) {
                continue;
            }

            $qty = (int) ($item['quantity'] ?? 1);
            $subtotal = (float) ($item['subtotal'] ?? (($item['price'] ?? 0.0) * $qty));

            $matchingQty += $qty;
            $matchingSubtotal += $subtotal;
        }

        if ($matchingQty <= 0 || $matchingSubtotal <= 0) {
            return null;
        }

        $tieredSteps = $conditions['tiered_steps'] ?? $conditions['tiers'] ?? [];
        $matchedDiscountPercent = 0.0;
        $matchedMinQty = 0;

        if (! empty($tieredSteps) && is_array($tieredSteps)) {
            // Sort steps descending by threshold
            usort($tieredSteps, function ($a, $b) {
                $qtyA = (int) ($a['min_qty'] ?? $a['qty'] ?? $a['quantity'] ?? 0);
                $qtyB = (int) ($b['min_qty'] ?? $b['qty'] ?? $b['quantity'] ?? 0);

                return $qtyB <=> $qtyA;
            });

            foreach ($tieredSteps as $step) {
                $stepMinQty = (int) ($step['min_qty'] ?? $step['qty'] ?? $step['quantity'] ?? 0);
                $stepDiscount = (float) ($step['discount'] ?? $step['percent'] ?? $step['discount_percent'] ?? $step['discount_percentage'] ?? $step['value'] ?? 0.0);

                if ($matchingQty >= $stepMinQty && $stepDiscount > 0) {
                    $matchedDiscountPercent = $stepDiscount;
                    $matchedMinQty = $stepMinQty;
                    break;
                }
            }
        }

        // Fallback to rule default discount_value if min_quantity met
        if ($matchedDiscountPercent <= 0 && $rule->min_quantity > 0 && $matchingQty >= $rule->min_quantity) {
            $matchedDiscountPercent = (float) $rule->discount_value;
            $matchedMinQty = (int) $rule->min_quantity;
        }

        if ($matchedDiscountPercent <= 0) {
            return null;
        }

        $percent = min(100.0, max(0.0, $matchedDiscountPercent));
        $rawDiscount = $matchingSubtotal * ($percent / 100.0);

        $discountAmount = $rawDiscount;
        if ($rule->max_discount_amount !== null && $rule->max_discount_amount > 0) {
            $discountAmount = min($discountAmount, (float) $rule->max_discount_amount);
        }

        $discountAmount = min($discountAmount, $matchingSubtotal);
        $discountAmount = min($discountAmount, $eligibleSubtotal);
        $discountAmount = round($discountAmount, 2);

        if ($discountAmount <= 0) {
            return null;
        }

        $description = sprintf(
            'Chiết khấu bậc thang %s%% cho %d+ sản phẩm (Đã mua %d sp)',
            $percent,
            $matchedMinQty,
            $matchingQty
        );

        return new AppliedRuleDiscount(
            ruleId: (int) $rule->id,
            ruleName: (string) ($rule->name ?? ''),
            ruleCode: $rule->code,
            actionType: PromotionRule::ACTION_TIERED_QUANTITY,
            discountAmount: $discountAmount,
            target: 'item',
            description: $description,
            isCoupon: $rule->isCoupon(),
            rule: $rule
        );
    }
}
