<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PromotionRule;
use App\Models\PromotionUsage;
use App\Observers\PromotionRuleObserver;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('promotion rule constants are correctly defined', function () {
    expect(PromotionRule::RULE_TYPE_CATALOG)->toBe('catalog_rule');
    expect(PromotionRule::RULE_TYPE_CART)->toBe('cart_rule');

    expect(PromotionRule::ACTION_PERCENTAGE)->toBe('percentage');
    expect(PromotionRule::ACTION_FIXED_AMOUNT)->toBe('fixed_amount');
    expect(PromotionRule::ACTION_BUY_X_GET_Y)->toBe('buy_x_get_y');
    expect(PromotionRule::ACTION_TIERED_QUANTITY)->toBe('tiered_quantity');
    expect(PromotionRule::ACTION_FREE_SHIPPING)->toBe('free_shipping');

    expect(PromotionRule::TIER_ALL)->toBe('all');
    expect(PromotionRule::TIER_BRONZE)->toBe('bronze');
    expect(PromotionRule::TIER_SILVER)->toBe('silver');
    expect(PromotionRule::TIER_GOLD)->toBe('gold');
    expect(PromotionRule::TIER_PLATINUM)->toBe('platinum');
    expect(PromotionRule::TIER_FIRST_TIME)->toBe('first_time');
});

test('can create and persist promotion rule with full fillable attributes and casts', function () {
    $startsAt = Carbon::parse('2026-08-20 00:00:00');
    $endsAt = Carbon::parse('2026-08-31 23:59:59');

    $rule = PromotionRule::create([
        'name' => 'Đại Tiệc Mùa Hè 2026',
        'code' => 'SUMMER2026',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 15.5,
        'max_discount_amount' => 500000.0,
        'min_order_amount' => 1000000.0,
        'min_quantity' => 2,
        'conditions' => [
            'category_ids' => [1, 2],
            'tiered_steps' => [
                ['qty' => 2, 'percent' => 5],
                ['qty' => 4, 'percent' => 10],
            ],
        ],
        'target_customer_tier' => PromotionRule::TIER_ALL,
        'usage_limit' => 100,
        'usage_limit_per_user' => 2,
        'used_count' => 10,
        'priority' => 5,
        'stop_further_rules' => true,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'is_active' => true,
    ]);

    expect($rule->exists)->toBeTrue();
    expect($rule->id)->toBeGreaterThan(0);
    expect($rule->name)->toBe('Đại Tiệc Mùa Hè 2026');
    expect($rule->code)->toBe('SUMMER2026');
    expect($rule->rule_type)->toBe(PromotionRule::RULE_TYPE_CART);
    expect($rule->action_type)->toBe(PromotionRule::ACTION_PERCENTAGE);

    // Cast assertions
    expect($rule->discount_value)->toBeFloat()->toBe(15.5);
    expect($rule->max_discount_amount)->toBeFloat()->toBe(500000.0);
    expect($rule->min_order_amount)->toBeFloat()->toBe(1000000.0);
    expect($rule->min_quantity)->toBeInt()->toBe(2);
    expect($rule->conditions)->toBeArray()->toHaveKeys(['category_ids', 'tiered_steps']);
    expect($rule->usage_limit)->toBeInt()->toBe(100);
    expect($rule->usage_limit_per_user)->toBeInt()->toBe(2);
    expect($rule->used_count)->toBeInt()->toBe(10);
    expect($rule->priority)->toBeInt()->toBe(5);
    expect($rule->stop_further_rules)->toBeBool()->toBeTrue();
    expect($rule->is_active)->toBeBool()->toBeTrue();
    expect($rule->starts_at)->toBeInstanceOf(Carbon::class);
    expect($rule->ends_at)->toBeInstanceOf(Carbon::class);

    $this->assertDatabaseHas('promotion_rules', [
        'id' => $rule->id,
        'code' => 'SUMMER2026',
        'name' => 'Đại Tiệc Mùa Hè 2026',
    ]);
});

test('default promotion rule attributes match specification', function () {
    $rule = PromotionRule::create([
        'name' => 'Default Promotion',
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
    ]);

    expect($rule->rule_type)->toBe(PromotionRule::RULE_TYPE_CART);
    expect($rule->discount_value)->toBe(0.0);
    expect($rule->max_discount_amount)->toBeNull();
    expect($rule->min_order_amount)->toBe(0.0);
    expect($rule->min_quantity)->toBe(0);
    expect($rule->conditions)->toBeNull();
    expect($rule->target_customer_tier)->toBe(PromotionRule::TIER_ALL);
    expect($rule->usage_limit)->toBeNull();
    expect($rule->usage_limit_per_user)->toBe(1);
    expect($rule->used_count)->toBe(0);
    expect($rule->priority)->toBe(0);
    expect($rule->stop_further_rules)->toBeFalse();
    expect($rule->starts_at)->toBeNull();
    expect($rule->ends_at)->toBeNull();
    expect($rule->is_active)->toBeTrue();
});

test('unique code constraint rejects duplicate non-null coupon codes', function () {
    PromotionRule::create([
        'name' => 'First Promo',
        'code' => 'UNIQUECODE',
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    expect(function () {
        PromotionRule::create([
            'name' => 'Duplicate Promo',
            'code' => 'UNIQUECODE',
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
        ]);
    })->toThrow(QueryException::class);
});

test('multiple rules can be created with null coupon code', function () {
    $rule1 = PromotionRule::create([
        'name' => 'Auto Promo 1',
        'code' => null,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    $rule2 = PromotionRule::create([
        'name' => 'Auto Promo 2',
        'code' => null,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
    ]);

    expect($rule1->exists)->toBeTrue();
    expect($rule2->exists)->toBeTrue();
});

test('scopeActive filters by is_active, scheduling window, and usage limits', function () {
    Carbon::setTestNow('2026-08-20 12:00:00');

    // 1. Active with no scheduling boundaries
    $activeIndefinite = PromotionRule::create([
        'name' => 'Active Indefinite',
        'is_active' => true,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    // 2. Active currently within valid window
    $activeWindow = PromotionRule::create([
        'name' => 'Active Window',
        'is_active' => true,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'starts_at' => Carbon::parse('2026-08-15 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-25 00:00:00'),
    ]);

    // 3. Active with available usage limit
    $activeUsage = PromotionRule::create([
        'name' => 'Active Usage Available',
        'is_active' => true,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'usage_limit' => 10,
        'used_count' => 5,
    ]);

    // 4. Inactive rule (should be excluded)
    $inactive = PromotionRule::create([
        'name' => 'Inactive Rule',
        'is_active' => false,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    // 5. Future scheduled rule (should be excluded)
    $futureScheduled = PromotionRule::create([
        'name' => 'Future Promo',
        'is_active' => true,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'starts_at' => Carbon::parse('2026-08-25 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-30 00:00:00'),
    ]);

    // 6. Expired rule (should be excluded)
    $expired = PromotionRule::create([
        'name' => 'Expired Promo',
        'is_active' => true,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'starts_at' => Carbon::parse('2026-08-01 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-19 23:59:59'),
    ]);

    // 7. Exhausted usage rule (should be excluded)
    $exhausted = PromotionRule::create([
        'name' => 'Exhausted Promo',
        'is_active' => true,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'usage_limit' => 10,
        'used_count' => 10,
    ]);

    $activeRules = PromotionRule::active()->get();
    $activeIds = $activeRules->pluck('id')->all();

    expect($activeRules)->toHaveCount(3);
    expect($activeIds)->toContain($activeIndefinite->id, $activeWindow->id, $activeUsage->id);
    expect($activeIds)->not->toContain($inactive->id, $futureScheduled->id, $expired->id, $exhausted->id);

    Carbon::setTestNow();
});

test('scopeCatalogRules and scopeCartRules filter rules by classification', function () {
    $catalog1 = PromotionRule::create([
        'name' => 'Catalog Promo 1',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    $catalog2 = PromotionRule::create([
        'name' => 'Catalog Promo 2',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    $cart1 = PromotionRule::create([
        'name' => 'Cart Promo 1',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
    ]);

    $catalogResults = PromotionRule::catalogRules()->get();
    expect($catalogResults)->toHaveCount(2);
    expect($catalogResults->pluck('id')->all())->toBe([$catalog1->id, $catalog2->id]);

    $cartResults = PromotionRule::cartRules()->get();
    expect($cartResults)->toHaveCount(1);
    expect($cartResults->first()->id)->toBe($cart1->id);
});

test('scopeOrderedByPriority sorts rules ascending by priority', function () {
    $ruleLow = PromotionRule::create([
        'name' => 'Low Priority',
        'priority' => 20,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    $ruleHigh = PromotionRule::create([
        'name' => 'High Priority',
        'priority' => 1,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    $ruleMed = PromotionRule::create([
        'name' => 'Medium Priority',
        'priority' => 10,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    $ordered = PromotionRule::orderedByPriority()->get();
    expect($ordered->pluck('id')->all())->toBe([
        $ruleHigh->id,
        $ruleMed->id,
        $ruleLow->id,
    ]);
});

test('isApplicableToCustomer validates active status and scheduling dates', function () {
    Carbon::setTestNow('2026-08-20 12:00:00');

    $inactiveRule = PromotionRule::create([
        'name' => 'Inactive',
        'is_active' => false,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    expect($inactiveRule->isApplicableToCustomer(null, 1000000, 2))->toBeFalse();

    $futureRule = PromotionRule::create([
        'name' => 'Future',
        'is_active' => true,
        'starts_at' => Carbon::parse('2026-08-25 00:00:00'),
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    expect($futureRule->isApplicableToCustomer(null, 1000000, 2))->toBeFalse();

    $expiredRule = PromotionRule::create([
        'name' => 'Expired',
        'is_active' => true,
        'ends_at' => Carbon::parse('2026-08-15 00:00:00'),
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    expect($expiredRule->isApplicableToCustomer(null, 1000000, 2))->toBeFalse();

    $validRule = PromotionRule::create([
        'name' => 'Valid',
        'is_active' => true,
        'starts_at' => Carbon::parse('2026-08-15 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-25 00:00:00'),
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    expect($validRule->isApplicableToCustomer(null, 1000000, 2))->toBeTrue();

    Carbon::setTestNow();
});

test('isApplicableToCustomer validates minimum order subtotal and minimum item quantity', function () {
    $rule = PromotionRule::create([
        'name' => 'Min Conditions Rule',
        'is_active' => true,
        'min_order_amount' => 500000.0,
        'min_quantity' => 3,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    // Subtotal below threshold
    expect($rule->isApplicableToCustomer(null, 499000.0, 3))->toBeFalse();

    // Quantity below threshold
    expect($rule->isApplicableToCustomer(null, 600000.0, 2))->toBeFalse();

    // Both conditions satisfied
    expect($rule->isApplicableToCustomer(null, 500000.0, 3))->toBeTrue();
    expect($rule->isApplicableToCustomer(null, 800000.0, 5))->toBeTrue();
});

test('isApplicableToCustomer validates customer tier segments', function () {
    $allRule = PromotionRule::create([
        'name' => 'All Tiers',
        'target_customer_tier' => PromotionRule::TIER_ALL,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);
    expect($allRule->isApplicableToCustomer(null, 100000, 1))->toBeTrue();

    $firstTimeRule = PromotionRule::create([
        'name' => 'First Time Customers Only',
        'target_customer_tier' => PromotionRule::TIER_FIRST_TIME,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    // Guest with no prior order
    expect($firstTimeRule->isApplicableToCustomer(null, 100000, 1, [], 'newguest@example.com'))->toBeTrue();

    // Customer with 0 orders
    $newCustomer = Customer::create([
        'name' => 'New Buyer',
        'email' => 'newbuyer@example.com',
        'password' => 'secret123',
    ]);
    expect($firstTimeRule->isApplicableToCustomer($newCustomer, 100000, 1))->toBeTrue();

    // Customer with existing order
    Order::create([
        'customer_id' => $newCustomer->id,
        'order_number' => 'SO-1001',
        'status' => OrderStatus::Delivered,
        'email' => $newCustomer->email,
        'customer_name' => $newCustomer->name,
        'phone' => '0901234567',
        'address' => '123 Main St',
        'subtotal' => 500000,
        'discount_amount' => 0,
        'shipping_fee' => 30000,
        'total_amount' => 530000,
    ]);
    expect($firstTimeRule->isApplicableToCustomer($newCustomer, 100000, 1))->toBeFalse();

    // VIP Gold rule
    $goldRule = PromotionRule::create([
        'name' => 'VIP Gold Only',
        'target_customer_tier' => PromotionRule::TIER_GOLD,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    // Guest is not VIP
    expect($goldRule->isApplicableToCustomer(null, 100000, 1))->toBeFalse();

    // Customer with total spent >= 20M
    $vipCustomer = Customer::create([
        'name' => 'VIP Customer',
        'email' => 'vip@example.com',
        'password' => 'secret123',
    ]);
    Order::create([
        'customer_id' => $vipCustomer->id,
        'order_number' => 'SO-VIP-1',
        'status' => OrderStatus::Delivered,
        'email' => $vipCustomer->email,
        'customer_name' => $vipCustomer->name,
        'phone' => '0909999999',
        'address' => '456 Gold Ave',
        'subtotal' => 25000000,
        'discount_amount' => 0,
        'shipping_fee' => 0,
        'total_amount' => 25000000,
    ]);
    expect($goldRule->isApplicableToCustomer($vipCustomer, 100000, 1))->toBeTrue();
});

test('isApplicableToCustomer validates category and product restrictions in conditions', function () {
    $categoryA = Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
    $categoryB = Category::create(['name' => 'Lighting', 'slug' => 'lighting']);

    $categoryRule = PromotionRule::create([
        'name' => 'Lighting Discount',
        'conditions' => ['category_ids' => [$categoryB->id]],
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    // Cart with only Category A -> false
    expect($categoryRule->isApplicableToCustomer(null, 100000, 1, [$categoryA->id]))->toBeFalse();

    // Cart with Category B -> true
    expect($categoryRule->isApplicableToCustomer(null, 100000, 1, [$categoryA->id, $categoryB->id]))->toBeTrue();
});

test('isApplicableToCustomer validates per-user usage limits against promotion_usages', function () {
    $rule = PromotionRule::create([
        'name' => 'One-Time Coupon',
        'usage_limit_per_user' => 1,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
    ]);

    $customer = Customer::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
    ]);

    // Initial check -> true
    expect($rule->isApplicableToCustomer($customer, 100000, 1))->toBeTrue();
    expect($rule->isApplicableToCustomer(null, 100000, 1, [], 'guest@example.com'))->toBeTrue();

    // Record usage for customer
    $rule->recordUsage($customer->id, null, $customer->email, 50000.0);

    // Customer check -> false (limit reached)
    expect($rule->isApplicableToCustomer($customer, 100000, 1))->toBeFalse();

    // Guest with same email -> false
    expect($rule->isApplicableToCustomer(null, 100000, 1, [], $customer->email))->toBeFalse();

    // Different guest -> still true
    expect($rule->isApplicableToCustomer(null, 100000, 1, [], 'other@example.com'))->toBeTrue();
});

test('recordUsage creates promotion_usages record and increments used_count atomically', function () {
    $rule = PromotionRule::create([
        'name' => 'Promo 50K',
        'used_count' => 0,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
    ]);

    $customer = Customer::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $usage = $rule->recordUsage($customer->id, null, 'jane@example.com', 50000.0);

    expect($usage)->toBeInstanceOf(PromotionUsage::class);
    expect($usage->promotion_rule_id)->toBe($rule->id);
    expect($usage->customer_id)->toBe($customer->id);
    expect($usage->email)->toBe('jane@example.com');
    expect($usage->discount_amount)->toBeFloat()->toBe(50000.0);

    $this->assertDatabaseHas('promotion_usages', [
        'promotion_rule_id' => $rule->id,
        'customer_id' => $customer->id,
        'email' => 'jane@example.com',
        'discount_amount' => 50000.0,
    ]);

    $rule->refresh();
    expect($rule->used_count)->toBe(1);

    // Call recordUsage again
    $rule->recordUsage($customer->id, null, 'jane@example.com', 50000.0);
    $rule->refresh();
    expect($rule->used_count)->toBe(2);
});

test('PromotionRuleObserver invalidates active catalog rules cache on save, delete, and restore', function () {
    $cacheKey = PromotionRuleObserver::CATALOG_RULES_CACHE_KEY;

    // 1. Invalidate on create
    Cache::put($cacheKey, ['mock_catalog_rule_payload'], 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    $rule = PromotionRule::create([
        'name' => 'Catalog Promo',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();

    // 2. Invalidate on update
    Cache::put($cacheKey, ['mock_catalog_rule_payload'], 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    $rule->update(['discount_value' => 20.0]);
    expect(Cache::has($cacheKey))->toBeFalse();

    // 3. Invalidate on delete
    Cache::put($cacheKey, ['mock_catalog_rule_payload'], 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    $rule->delete();
    expect(Cache::has($cacheKey))->toBeFalse();
});
