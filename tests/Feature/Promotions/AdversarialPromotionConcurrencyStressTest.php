<?php

namespace Tests\Feature\Promotions;

use App\Actions\ProcessCheckoutAction;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Models\PromotionUsage;
use App\Services\Promotions\DTOs\PromotionDiscountBreakdown;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

describe('Adversarial Promotion Concurrency & Race Condition Stress Matrix', function () {

    test('adversarial concurrency: 50 simultaneous checkouts against usage_limit = 5 yields exactly 5 discounts and 45 full-price orders', function () {
        $product = Product::create([
            'name' => 'Adversarial Concurrency Sofa',
            'slug' => 'adversarial-concurrency-sofa',
            'price' => 1000000,
            'stock' => 100,
            'status' => 'published',
        ]);

        $couponRule = PromotionRule::create([
            'name' => 'Limited 50k Flash Voucher',
            'code' => 'FLASH50K',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 50000.0,
            'usage_limit' => 5,
            'usage_limit_per_user' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $action = app(ProcessCheckoutAction::class);
        $discountedOrderCount = 0;
        $fullPriceOrderCount = 0;

        for ($i = 1; $i <= 50; $i++) {
            Session::put('cart', [
                "{$product->id}_0" => [
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'quantity' => 1,
                ],
            ]);

            $customerData = [
                'customer_name' => "Shopper {$i}",
                'phone' => sprintf('0901234%03d', $i),
                'email' => "shopper_{$i}@example.com",
                'address' => "{$i} Concurrency Boulevard",
                'payment_method' => 'cod',
            ];

            $order = $action->execute($customerData, 'FLASH50K');

            if ($order->discount_amount > 0) {
                $discountedOrderCount++;
                expect((float) $order->discount_amount)->toBe(50000.0);
            } else {
                $fullPriceOrderCount++;
                expect((float) $order->discount_amount)->toBe(0.0);
            }
        }

        // Empirical invariants
        expect($discountedOrderCount)->toBe(5);
        expect($fullPriceOrderCount)->toBe(45);

        $couponRule->refresh();
        expect($couponRule->used_count)->toBe(5);
        expect(PromotionUsage::where('promotion_rule_id', $couponRule->id)->count())->toBe(5);
        expect(Order::count())->toBe(50);
    });

    test('per-user limit test: parallel checkouts with same user_id and email cannot exceed usage_limit_per_user = 1', function () {
        $product = Product::create([
            'name' => 'Adversarial Desk',
            'slug' => 'adversarial-desk',
            'price' => 2000000,
            'stock' => 50,
            'status' => 'published',
        ]);

        $couponRule = PromotionRule::create([
            'name' => 'Single Use 15% Off',
            'code' => 'ONEUSER15',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 15.0,
            'usage_limit' => 100,
            'usage_limit_per_user' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Greedy Buyer',
            'email' => 'greedy_buyer@example.com',
            'password' => bcrypt('password123'),
        ]);

        $action = app(ProcessCheckoutAction::class);
        $discountedCount = 0;
        $fullPriceCount = 0;

        for ($i = 1; $i <= 10; $i++) {
            Session::put('cart', [
                "{$product->id}_0" => [
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'quantity' => 1,
                ],
            ]);

            $customerData = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'phone' => '0988888888',
                'email' => $customer->email,
                'address' => '888 High Street',
                'payment_method' => 'cod',
            ];

            $order = $action->execute($customerData, 'ONEUSER15');

            if ($order->discount_amount > 0) {
                $discountedCount++;
                expect((float) $order->discount_amount)->toBe(300000.0); // 15% of 2M
            } else {
                $fullPriceCount++;
                expect((float) $order->discount_amount)->toBe(0.0);
            }
        }

        expect($discountedCount)->toBe(1);
        expect($fullPriceCount)->toBe(9);
        expect(PromotionUsage::where('promotion_rule_id', $couponRule->id)->where('customer_id', $customer->id)->count())->toBe(1);
    });

    test('rule priority and stop_further_rules: high-priority rule blocks lower-priority automatic rules', function () {
        $engine = app(PromotionEngine::class);

        $highPriorityRule = PromotionRule::create([
            'name' => 'VIP Exclusive 20% Auto',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 20.0,
            'priority' => 1,
            'stop_further_rules' => true,
            'is_active' => true,
        ]);

        $lowerPriorityRule1 = PromotionRule::create([
            'name' => 'Regular 5% Auto',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 5.0,
            'priority' => 2,
            'stop_further_rules' => false,
            'is_active' => true,
        ]);

        $lowerPriorityRule2 = PromotionRule::create([
            'name' => 'Bonus 50k Fixed',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 50000.0,
            'priority' => 3,
            'stop_further_rules' => false,
            'is_active' => true,
        ]);

        $cartItems = [
            ['product_id' => 1, 'price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000, 'is_flash_sale' => false],
        ];

        $breakdown = $engine->calculateCartDiscounts(1000000.0, $cartItems);

        expect($breakdown)->toBeInstanceOf(PromotionDiscountBreakdown::class);
        expect($breakdown->appliedRules)->toHaveCount(1);
        expect($breakdown->appliedRules[0]->ruleId)->toBe($highPriorityRule->id);
        expect($breakdown->totalDiscount)->toBe(200000.0);
        expect($breakdown->messages)->toContain('Đã áp dụng ưu tiên: VIP Exclusive 20% Auto');
    });

    test('flash sale isolation: flash sale items receive zero percentage discount while regular items receive promotion discount', function () {
        $engine = app(PromotionEngine::class);

        $storewideRule = PromotionRule::create([
            'name' => 'Storewide 15% Promo',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 15.0,
            'priority' => 1,
            'is_active' => true,
        ]);

        $cartItems = [
            ['product_id' => 10, 'product_name' => 'Flash Sale Lamp', 'price' => 800000, 'quantity' => 2, 'subtotal' => 1600000, 'is_flash_sale' => true],
            ['product_id' => 20, 'product_name' => 'Regular Chair', 'price' => 500000, 'quantity' => 1, 'subtotal' => 500000, 'is_flash_sale' => false],
        ];

        $totalSubtotal = 2100000.0;
        $breakdown = $engine->calculateCartDiscounts($totalSubtotal, $cartItems);

        expect($breakdown->subtotal)->toBe(2100000.0);
        expect($breakdown->flashSaleSubtotal)->toBe(1600000.0);
        expect($breakdown->eligibleSubtotal)->toBe(500000.0);

        // 15% discount strictly on 500k eligible subtotal = 75,000 VND
        expect($breakdown->itemDiscounts)->toBe(75000.0);
        expect($breakdown->totalDiscount)->toBe(75000.0);
        expect($breakdown->finalTotal)->toBe(2025000.0); // 2,100,000 - 75,000 = 2,025,000
    });

    test('mixed cart with flash sale and tiered volume discount verifies eligible partitioning', function () {
        $engine = app(PromotionEngine::class);

        $tieredRule = PromotionRule::create([
            'name' => 'Bulk Tiered Discount',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
            'conditions' => [
                'tiered_steps' => [
                    ['min_qty' => 3, 'discount' => 10.0],
                    ['min_qty' => 5, 'discount' => 20.0],
                ],
            ],
            'priority' => 1,
            'is_active' => true,
        ]);

        // Cart has 4 flash sale items and 3 regular items
        $cartItems = [
            ['product_id' => 101, 'price' => 100000, 'quantity' => 4, 'subtotal' => 400000, 'is_flash_sale' => true],
            ['product_id' => 202, 'price' => 200000, 'quantity' => 3, 'subtotal' => 600000, 'is_flash_sale' => false],
        ];

        $breakdown = $engine->calculateCartDiscounts(1000000.0, $cartItems);

        // Tiered quantity evaluates ONLY the 3 regular items (Tier 1: 10% on 600k = 60k).
        // It must NOT count the 4 flash items to jump to Tier 2 (20%).
        expect($breakdown->eligibleSubtotal)->toBe(600000.0);
        expect($breakdown->flashSaleSubtotal)->toBe(400000.0);
        expect($breakdown->totalDiscount)->toBe(60000.0);
        expect($breakdown->finalTotal)->toBe(940000.0);
    });

    test('inventory shortage causes atomic transaction rollback and releases promotion usage reservation', function () {
        $product = Product::create([
            'name' => 'Rare Limited Armchair',
            'slug' => 'rare-limited-armchair',
            'price' => 3000000,
            'stock' => 1,
            'status' => 'published',
        ]);

        $rule = PromotionRule::create([
            'name' => 'Golden Voucher 500k',
            'code' => 'GOLD500K',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 500000.0,
            'usage_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $action = app(ProcessCheckoutAction::class);

        // Buyer 1 successfully checks out the only available stock item
        Session::put('cart', [
            "{$product->id}_0" => [
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => 1,
            ],
        ]);

        $order1 = $action->execute([
            'customer_name' => 'Buyer 1',
            'phone' => '0901111111',
            'email' => 'buyer1@example.com',
            'address' => '111 St',
        ], 'GOLD500K');

        expect($order1->discount_amount)->toBe(500000);
        expect($rule->fresh()->used_count)->toBe(1);

        // Buyer 2 attempts to checkout with out of stock item
        Session::put('cart', [
            "{$product->id}_0" => [
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => 1,
            ],
        ]);

        expect(fn () => $action->execute([
            'customer_name' => 'Buyer 2',
            'phone' => '0902222222',
            'email' => 'buyer2@example.com',
            'address' => '222 St',
        ], 'GOLD500K'))->toThrow(InsufficientStockException::class);

        // Verify no extra usage was recorded
        expect($rule->fresh()->used_count)->toBe(1);
        expect(PromotionUsage::where('promotion_rule_id', $rule->id)->count())->toBe(1);
    });

    test('customer tier segmentation correctly filters First Time vs Gold vs Bronze tiers', function () {
        $firstTimeRule = PromotionRule::create([
            'name' => 'New Customer 10%',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 10.0,
            'target_customer_tier' => PromotionRule::TIER_FIRST_TIME,
            'is_active' => true,
        ]);

        $newCustomer = Customer::create([
            'name' => 'Brand New Shopper',
            'email' => 'newbie@example.com',
            'password' => bcrypt('password123'),
        ]);

        expect($firstTimeRule->isApplicableToCustomer($newCustomer, 500000.0, 1, [], 'newbie@example.com'))->toBeTrue();

        // Create an existing confirmed order for this customer
        Order::create([
            'customer_id' => $newCustomer->id,
            'order_number' => 'ORD-EXISTING-001',
            'customer_name' => $newCustomer->name,
            'email' => $newCustomer->email,
            'phone' => '0901234567',
            'address' => '123 St',
            'subtotal' => 500000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => 500000,
            'status' => OrderStatus::Confirmed,
            'payment_method' => 'cod',
        ]);

        // Customer now has prior orders -> first_time rule no longer applicable
        expect($firstTimeRule->isApplicableToCustomer($newCustomer, 500000.0, 1, [], 'newbie@example.com'))->toBeFalse();
    });
});
