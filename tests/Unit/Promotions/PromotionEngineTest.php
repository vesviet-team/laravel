<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Observers\PromotionRuleObserver;
use App\Services\Promotions\DTOs\PromotedPriceResult;
use App\Services\Promotions\DTOs\PromotionDiscountBreakdown;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->engine = app(PromotionEngine::class);
});

test('pipeline executes automatic rules in strict priority ascending order', function () {
    $ruleLow = PromotionRule::create([
        'name' => 'Low Priority 10k Fixed',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 10000.0,
        'priority' => 10,
        'is_active' => true,
    ]);

    $ruleHigh = PromotionRule::create([
        'name' => 'High Priority 5% Combo',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 5.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    $cartItems = [['price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000]];
    $breakdown = $this->engine->calculateCartDiscounts(1000000.0, $cartItems);

    expect($breakdown)->toBeInstanceOf(PromotionDiscountBreakdown::class);
    expect($breakdown->appliedRules)->toHaveCount(2);
    expect($breakdown->appliedRules[0]->ruleId)->toBe($ruleHigh->id);
    expect($breakdown->appliedRules[1]->ruleId)->toBe($ruleLow->id);
    expect($breakdown->totalDiscount)->toBe(60000.0); // 50k (5%) + 10k = 60k
});

test('stop_further_rules circuit breaker prevents lower priority rules from applying', function () {
    $ruleA = PromotionRule::create([
        'name' => 'Exclusive 10% Off',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'priority' => 1,
        'stop_further_rules' => true,
        'is_active' => true,
    ]);

    $ruleB = PromotionRule::create([
        'name' => 'Blocked 50k Fixed',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 50000.0,
        'priority' => 2,
        'stop_further_rules' => false,
        'is_active' => true,
    ]);

    $cartItems = [['price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000]];
    $breakdown = $this->engine->calculateCartDiscounts(1000000.0, $cartItems);

    expect($breakdown->appliedRules)->toHaveCount(1);
    expect($breakdown->appliedRules[0]->ruleId)->toBe($ruleA->id);
    expect($breakdown->totalDiscount)->toBe(100000.0);
});

test('strictly isolates flash sale items from percentage cart promo rules', function () {
    PromotionRule::create([
        'name' => 'Storewide 10% Off',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'is_active' => true,
    ]);

    $cartItems = [
        ['price' => 500000, 'quantity' => 1, 'subtotal' => 500000, 'is_flash_sale' => true],
        ['price' => 300000, 'quantity' => 1, 'subtotal' => 300000, 'is_flash_sale' => false],
    ];

    $breakdown = $this->engine->calculateCartDiscounts(800000.0, $cartItems);

    // 10% applied strictly on 300k = 30k (Flash sale 500k is isolated)
    expect($breakdown->itemDiscounts)->toBe(30000.0);
    expect($breakdown->totalDiscount)->toBe(30000.0);
    expect($breakdown->flashSaleSubtotal)->toBe(500000.0);
    expect($breakdown->eligibleSubtotal)->toBe(300000.0);
});

test('applies coupon code discount accurately on eligible subtotal', function () {
    $couponRule = PromotionRule::create([
        'name' => 'VIP 20% Coupon',
        'code' => 'VIP20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'is_active' => true,
    ]);

    $cartItems = [
        ['price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000, 'is_flash_sale' => false],
    ];

    $breakdown = $this->engine->calculateCartDiscounts(1000000.0, $cartItems, 'VIP20');

    expect($breakdown->hasCouponApplied())->toBeTrue();
    expect($breakdown->couponDiscount)->toBe(200000.0);
    expect($breakdown->totalDiscount)->toBe(200000.0);
    expect($breakdown->finalTotal)->toBe(800000.0);
});

test('resolveProductPromotedPrice calculates strike price and badge for catalog rules', function () {
    $category = Category::create(['name' => 'Lighting', 'slug' => 'lighting', 'is_active' => true]);
    $product = Product::create([
        'name' => 'Pendant Lamp',
        'slug' => 'pendant-lamp',
        'price' => 1000000,
        'stock' => 10,
        'category_id' => $category->id,
        'status' => 'published',
    ]);

    $catalogRule = PromotionRule::create([
        'name' => 'Lighting Season 15%',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 15.0,
        'conditions' => ['category_ids' => [$category->id]],
        'priority' => 1,
        'is_active' => true,
    ]);

    $result = $this->engine->resolveProductPromotedPrice($product);

    expect($result)->toBeInstanceOf(PromotedPriceResult::class);
    expect($result->originalPrice)->toBe(1000000.0);
    expect($result->promotedPrice)->toBe(850000.0);
    expect($result->discountPercent)->toBe(15.0);
    expect($result->badgeLabel)->toBe('-15% PROMO');
    expect($result->ruleId)->toBe($catalogRule->id);
});

test('active catalog rules are cached and invalidated via PromotionRuleObserver', function () {
    $cacheKey = PromotionRuleObserver::CATALOG_RULES_CACHE_KEY;
    Cache::flush();

    PromotionRule::create([
        'name' => 'Catalog Promo',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'is_active' => true,
    ]);

    // Observer invalidates on create
    expect(Cache::has($cacheKey))->toBeFalse();

    // First call caches rules
    $rules = $this->engine->getActiveCatalogRules();
    expect($rules)->toHaveCount(1);
    expect(Cache::has($cacheKey))->toBeTrue();

    // Mutation triggers observer eviction
    $rules->first()->update(['discount_value' => 25.0]);
    expect(Cache::has($cacheKey))->toBeFalse();
});
