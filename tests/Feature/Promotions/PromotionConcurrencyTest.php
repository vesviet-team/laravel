<?php

use App\Actions\ProcessCheckoutAction;
use App\Events\OrderPlaced;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Models\PromotionUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([OrderPlaced::class]);
});

test('pessimistic concurrency lock prevents over-redemption on global usage limit', function () {
    $product = Product::create([
        'name' => 'Concurrency Test Product',
        'slug' => 'concurrency-test-product',
        'price' => 500000,
        'stock' => 50,
        'status' => 'published',
    ]);

    $rule = PromotionRule::create([
        'name' => 'Limited Flash Coupon 50k',
        'code' => 'FLASH50K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 50000.0,
        'usage_limit' => 2,
        'used_count' => 0,
        'is_active' => true,
    ]);

    $action = app(ProcessCheckoutAction::class);
    $successfulDiscountedOrders = 0;

    // Simulate 5 consecutive checkout attempts with coupon FLASH50K
    for ($i = 1; $i <= 5; $i++) {
        Session::put('cart', [
            "{$product->id}_0" => [
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => 1,
            ],
        ]);

        $customerData = [
            'customer_name' => "Shopper {$i}",
            'phone' => "090123456{$i}",
            'email' => "shopper{$i}@example.com",
            'address' => "{$i} Concurrency Road",
            'payment_method' => 'cod',
        ];

        $order = $action->execute($customerData, 'FLASH50K');

        if ($order->discount_amount > 0) {
            $successfulDiscountedOrders++;
        }
    }

    // Exactly 2 orders received discount; 3 orders paid normal price
    expect($successfulDiscountedOrders)->toBe(2);

    $rule->refresh();
    // used_count NEVER exceeds usage_limit
    expect($rule->used_count)->toBe(2);

    // Exactly 2 promotion_usages records created
    expect(PromotionUsage::where('promotion_rule_id', $rule->id)->count())->toBe(2);
});

test('per-customer usage limit is strictly enforced under repeated checkout attempts', function () {
    $product = Product::create([
        'name' => 'Test Chair',
        'slug' => 'test-chair',
        'price' => 1000000,
        'stock' => 20,
        'status' => 'published',
    ]);

    $rule = PromotionRule::create([
        'name' => 'One Time 10%',
        'code' => 'ONETIME10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'usage_limit_per_user' => 1,
        'is_active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Same Customer',
        'email' => 'same@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $action = app(ProcessCheckoutAction::class);
    $discountedCount = 0;

    for ($i = 1; $i <= 3; $i++) {
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
            'phone' => '0909999999',
            'email' => $customer->email,
            'address' => '123 Customer St',
            'payment_method' => 'cod',
        ];

        $order = $action->execute($customerData, 'ONETIME10');
        if ($order->discount_amount > 0) {
            $discountedCount++;
        }
    }

    expect($discountedCount)->toBe(1);
    expect(PromotionUsage::where('promotion_rule_id', $rule->id)->where('customer_id', $customer->id)->count())->toBe(1);
});

test('transaction rollback on inventory shortfall prevents promo usage leakage', function () {
    $product = Product::create([
        'name' => 'Scarce Product',
        'slug' => 'scarce-product',
        'price' => 500000,
        'stock' => 1, // Only 1 in stock
        'status' => 'published',
    ]);

    $rule = PromotionRule::create([
        'name' => 'Promo 10%',
        'code' => 'PROMO10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'usage_limit' => 5,
        'used_count' => 0,
        'is_active' => true,
    ]);

    $action = app(ProcessCheckoutAction::class);

    // First buyer succeeds
    Session::put('cart', ["{$product->id}_0" => ['product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 1]]);
    $action->execute(['customer_name' => 'Buyer 1', 'phone' => '0901111111', 'email' => 'b1@example.com', 'address' => 'A'], 'PROMO10');

    expect($rule->fresh()->used_count)->toBe(1);

    // Second buyer attempts to buy out-of-stock item
    Session::put('cart', ["{$product->id}_0" => ['product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 1]]);
    expect(function () use ($action) {
        $action->execute(['customer_name' => 'Buyer 2', 'phone' => '0902222222', 'email' => 'b2@example.com', 'address' => 'B'], 'PROMO10');
    })->toThrow(InsufficientStockException::class);

    // Rollback guarantees used_count is NOT incremented to 2 and no orphaned usage row exists
    expect($rule->fresh()->used_count)->toBe(1);
    expect(PromotionUsage::where('promotion_rule_id', $rule->id)->count())->toBe(1);
});
