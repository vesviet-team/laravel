<?php

use App\Models\PromotionRule;
use App\Services\Promotions\DTOs\AppliedRuleDiscount;
use App\Services\Promotions\Strategies\BuyXGetYStrategy;
use App\Services\Promotions\Strategies\FixedAmountStrategy;
use App\Services\Promotions\Strategies\FreeShippingStrategy;
use App\Services\Promotions\Strategies\PercentageWithCapStrategy;
use App\Services\Promotions\Strategies\TieredQuantityStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PercentageWithCapStrategy', function () {
    beforeEach(function () {
        $this->strategy = new PercentageWithCapStrategy;
    });

    test('calculates exact percentage discount on eligible subtotal', function () {
        $rule = new PromotionRule([
            'id' => 1,
            'name' => '10% Off',
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 10.0,
            'max_discount_amount' => null,
        ]);

        $result = $this->strategy->calculate($rule, 1000000.0, []);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(100000.0);
        expect($result->description)->toBe('Giảm 10%');
    });

    test('enforces upper ceiling cap when calculated discount exceeds max_discount_amount', function () {
        $rule = new PromotionRule([
            'id' => 2,
            'name' => '20% Off Max 300k',
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 20.0,
            'max_discount_amount' => 300000.0,
        ]);

        // 20% of 2,500,000 is 500,000, capped at 300,000
        $result = $this->strategy->calculate($rule, 2500000.0, []);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(300000.0);
        expect($result->description)->toContain('tối đa 300.000₫');
    });

    test('handles fractional percentages accurately', function () {
        $rule = new PromotionRule([
            'id' => 3,
            'name' => '12.5% Off',
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 12.5,
        ]);

        $result = $this->strategy->calculate($rule, 800000.0, []);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(100000.0);
    });

    test('returns null when discount_value is zero or negative', function () {
        $rule = new PromotionRule(['discount_value' => 0.0]);
        expect($this->strategy->calculate($rule, 1000000.0, []))->toBeNull();

        $ruleNeg = new PromotionRule(['discount_value' => -5.0]);
        expect($this->strategy->calculate($ruleNeg, 1000000.0, []))->toBeNull();
    });

    test('returns null when eligible subtotal is zero or negative', function () {
        $rule = new PromotionRule(['discount_value' => 15.0]);
        expect($this->strategy->calculate($rule, 0.0, []))->toBeNull();
        expect($this->strategy->calculate($rule, -10000.0, []))->toBeNull();
    });
});

describe('BuyXGetYStrategy', function () {
    beforeEach(function () {
        $this->strategy = new BuyXGetYStrategy;
    });

    test('applies 100% discount on reward item when trigger item quantity satisfied', function () {
        $rule = new PromotionRule([
            'id' => 10,
            'name' => 'Buy Desk Get Lamp Free',
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => 101,
                    'buy_quantity' => 2,
                    'get_product_id' => 202,
                    'get_quantity' => 1,
                    'discount_value' => 100.0,
                    'is_free' => true,
                ],
            ],
        ]);

        $cartItems = [
            ['product_id' => 101, 'price' => 1000000, 'quantity' => 2, 'subtotal' => 2000000],
            ['product_id' => 202, 'price' => 150000, 'quantity' => 1, 'subtotal' => 150000],
        ];

        $result = $this->strategy->calculate($rule, 2150000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(150000.0);
    });

    test('calculates multiple reward bundles proportionally', function () {
        $rule = new PromotionRule([
            'id' => 11,
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => 101,
                    'buy_quantity' => 2,
                    'get_product_id' => 202,
                    'get_quantity' => 1,
                    'discount_value' => 100.0,
                ],
            ],
        ]);

        // 6 trigger items = 3 entitled sets; cart contains 2 reward items -> 2 rewarded
        $cartItems = [
            ['product_id' => 101, 'price' => 1000000, 'quantity' => 6, 'subtotal' => 6000000],
            ['product_id' => 202, 'price' => 150000, 'quantity' => 2, 'subtotal' => 300000],
        ];

        $result = $this->strategy->calculate($rule, 6300000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(300000.0);
    });

    test('applies Buy X Get Y from same product pool', function () {
        $rule = new PromotionRule([
            'id' => 12,
            'name' => 'Buy 2 Get 1 Free Chair',
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => 50,
                    'buy_quantity' => 2,
                    'get_product_id' => 50,
                    'get_quantity' => 1,
                    'is_free' => true,
                ],
            ],
        ]);

        // 3 chairs total = 1 free (set size: 2+1=3)
        $cartItems = [
            ['product_id' => 50, 'price' => 200000, 'quantity' => 3, 'subtotal' => 600000],
        ];

        $result = $this->strategy->calculate($rule, 600000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(200000.0);
    });

    test('returns null when trigger item quantity is insufficient', function () {
        $rule = new PromotionRule([
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => 101,
                    'buy_quantity' => 2,
                    'get_product_id' => 202,
                ],
            ],
        ]);

        $cartItems = [
            ['product_id' => 101, 'price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000],
            ['product_id' => 202, 'price' => 150000, 'quantity' => 1, 'subtotal' => 150000],
        ];

        expect($this->strategy->calculate($rule, 1150000.0, $cartItems))->toBeNull();
    });

    test('returns null when reward item is not present in cart', function () {
        $rule = new PromotionRule([
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => 101,
                    'buy_quantity' => 2,
                    'get_product_id' => 202,
                ],
            ],
        ]);

        $cartItems = [
            ['product_id' => 101, 'price' => 1000000, 'quantity' => 4, 'subtotal' => 4000000],
        ];

        expect($this->strategy->calculate($rule, 4000000.0, $cartItems))->toBeNull();
    });

    test('respects max_rewards configuration limit', function () {
        $rule = new PromotionRule([
            'id' => 13,
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => 101,
                    'buy_quantity' => 1,
                    'get_product_id' => 202,
                    'get_quantity' => 1,
                    'max_rewards' => 2,
                ],
            ],
        ]);

        // 5 buy items and 5 reward items, but max_rewards is 2
        $cartItems = [
            ['product_id' => 101, 'price' => 500000, 'quantity' => 5, 'subtotal' => 2500000],
            ['product_id' => 202, 'price' => 100000, 'quantity' => 5, 'subtotal' => 500000],
        ];

        $result = $this->strategy->calculate($rule, 3000000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(200000.0); // 2 * 100k
    });
});

describe('TieredQuantityStrategy', function () {
    beforeEach(function () {
        $this->strategy = new TieredQuantityStrategy;
        $this->rule = new PromotionRule([
            'id' => 20,
            'name' => 'Tiered Bulk Discount',
            'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
            'conditions' => [
                'tiered_steps' => [
                    ['min_qty' => 2, 'discount' => 5.0],
                    ['min_qty' => 4, 'discount' => 10.0],
                    ['min_qty' => 6, 'discount' => 15.0],
                ],
            ],
        ]);
    });

    test('returns null when total eligible quantity is below lowest tier', function () {
        $cartItems = [['quantity' => 1, 'price' => 500000, 'subtotal' => 500000]];
        expect($this->strategy->calculate($this->rule, 500000.0, $cartItems))->toBeNull();
    });

    test('applies Tier 1 (5%) when quantity reaches step 1', function () {
        $cartItems = [['quantity' => 2, 'price' => 500000, 'subtotal' => 1000000]];
        $result = $this->strategy->calculate($this->rule, 1000000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(50000.0);
    });

    test('applies Tier 2 (10%) when quantity reaches step 2', function () {
        $cartItems = [['quantity' => 4, 'price' => 500000, 'subtotal' => 2000000]];
        $result = $this->strategy->calculate($this->rule, 2000000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(200000.0);
    });

    test('applies Tier 3 (15%) when quantity reaches top step', function () {
        $cartItems = [['quantity' => 8, 'price' => 500000, 'subtotal' => 4000000]];
        $result = $this->strategy->calculate($this->rule, 4000000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(600000.0);
    });

    test('falls back to rule discount_value and min_quantity if no steps defined', function () {
        $rule = new PromotionRule([
            'id' => 21,
            'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
            'discount_value' => 8.0,
            'min_quantity' => 3,
        ]);

        $cartItems = [['quantity' => 3, 'price' => 100000, 'subtotal' => 300000]];
        $result = $this->strategy->calculate($rule, 300000.0, $cartItems);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(24000.0);
    });
});

describe('FixedAmountStrategy', function () {
    beforeEach(function () {
        $this->strategy = new FixedAmountStrategy;
    });

    test('applies direct fixed amount deduction', function () {
        $rule = new PromotionRule([
            'id' => 30,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 50000.0,
        ]);

        $result = $this->strategy->calculate($rule, 500000.0, []);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(50000.0);
    });

    test('caps fixed discount at eligible subtotal to prevent negative cart', function () {
        $rule = new PromotionRule([
            'id' => 31,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 100000.0,
        ]);

        $result = $this->strategy->calculate($rule, 60000.0, []);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(60000.0);
    });

    test('returns null when discount_value is zero or negative', function () {
        $rule = new PromotionRule(['discount_value' => 0.0]);
        expect($this->strategy->calculate($rule, 500000.0, []))->toBeNull();
    });
});

describe('FreeShippingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new FreeShippingStrategy;
    });

    test('waives full shipping fee when discount_value is zero or null', function () {
        $rule = new PromotionRule([
            'id' => 40,
            'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
            'discount_value' => 0.0,
        ]);

        $result = $this->strategy->calculate($rule, 500000.0, [], 35000.0);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(35000.0);
        expect($result->target)->toBe('shipping');
    });

    test('caps shipping discount at actual shipping fee amount', function () {
        $rule = new PromotionRule([
            'id' => 41,
            'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
            'discount_value' => 50000.0,
        ]);

        $result = $this->strategy->calculate($rule, 500000.0, [], 30000.0);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(30000.0);
    });

    test('applies partial shipping discount when discount_value is less than shipping fee', function () {
        $rule = new PromotionRule([
            'id' => 42,
            'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
            'discount_value' => 20000.0,
        ]);

        $result = $this->strategy->calculate($rule, 500000.0, [], 35000.0);
        expect($result)->toBeInstanceOf(AppliedRuleDiscount::class);
        expect($result->discountAmount)->toBe(20000.0);
    });

    test('returns null when shipping fee is zero', function () {
        $rule = new PromotionRule([
            'id' => 43,
            'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        ]);

        expect($this->strategy->calculate($rule, 500000.0, [], 0.0))->toBeNull();
    });
});
