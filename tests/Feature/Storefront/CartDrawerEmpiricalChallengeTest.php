<?php

namespace Tests\Feature\Storefront;

use App\Livewire\CartDrawer;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Models\PromotionUsage;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->category = Category::create([
        'name' => 'Bàn Ghế Scandinavian',
        'slug' => 'ban-ghe',
        'is_active' => true,
    ]);

    $this->productA = Product::create([
        'name' => 'Ghế Lounge Gỗ Sồi',
        'slug' => 'ghe-lounge-go-soi',
        'sku' => 'CHR-001',
        'price' => 250000,
        'stock' => 20,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => false,
    ]);

    $this->productB = Product::create([
        'name' => 'Bàn Trà Tối Giản',
        'slug' => 'ban-tra-toi-gian',
        'sku' => 'TBL-001',
        'price' => 100000,
        'stock' => 15,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => true,
    ]);
});

// =========================================================================
// CHALLENGE 1: Smart Nudge Progress Bar (Empty, 50%, 100% / Exceeding)
// =========================================================================

test('Challenge 1.1: Smart Nudge with empty cart returns null (0% / inactive)', function () {
    PromotionRule::create([
        'name' => 'Freeship Đơn Từ 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', []);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->toBeNull();
    $component->assertDontSee('Mua thêm')
        ->assertDontSee('Freeship toàn quốc');
});

test('Challenge 1.2: Smart Nudge with subtotal 250,000đ / 500,000đ shows exactly 50% and gap 250,000đ', function () {
    PromotionRule::create([
        'name' => 'Freeship Đơn Từ 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1, // 1 * 250,000 = 250,000₫
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->not->toBeNull();
    expect($nudge['type'])->toBe('free_shipping');
    expect($nudge['progress_percent'])->toBe(50.0);
    expect($nudge['gap_amount'])->toBe(250000.0);
    expect($nudge['target_amount'])->toBe(500000.0);
    expect($nudge['is_completed'])->toBeFalse();
    $component->assertSee('Mua thêm 250.000₫ để nhận FREESHIP toàn quốc!');
    $component->assertSee('CÒN THIẾU 250.000₫');
});

test('Challenge 1.3: Smart Nudge with subtotal exceeding threshold (600,000đ / 500,000đ) shows 100% unlocked message', function () {
    PromotionRule::create([
        'name' => 'Freeship Đơn Từ 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // 2x Product A (500k) + 1x Product B (100k) = 600,000₫
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2,
        ],
        "{$this->productB->id}_0" => [
            'product_id' => $this->productB->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->not->toBeNull();
    expect($nudge['type'])->toBe('free_shipping');
    expect($nudge['progress_percent'])->toBe(100.0);
    expect($nudge['gap_amount'])->toBe(0.0);
    expect($nudge['is_completed'])->toBeTrue();
    $component->assertSee('Tuyệt vời! Đơn hàng của bạn đã đủ điều kiện Freeship toàn quốc!');
    $component->assertSee('FREESHIP ĐẠT 100%');
});

// =========================================================================
// CHALLENGE 2: Tiered Quantity Nudge (1 item for tier 2, 3 items for tier 4)
// =========================================================================

test('Challenge 2.1: Tiered Quantity Nudge with 1 item when tier requires 2 items (Step 1: 2 items -> 5%)', function () {
    PromotionRule::create([
        'name' => 'Chiết Khấu Số Lượng Lớn',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
        'discount_value' => 5.0,
        'conditions' => [
            'tiered_steps' => [
                ['min_qty' => 2, 'discount_percent' => 5],
                ['min_qty' => 4, 'discount_percent' => 10],
            ],
        ],
        'priority' => 1,
        'is_active' => true,
    ]);

    // 1 item in cart
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->not->toBeNull();
    expect($nudge['type'])->toBe('tiered_quantity');
    expect($nudge['gap_quantity'])->toBe(1);
    expect($nudge['progress_percent'])->toBe(50.0); // 1 / 2 = 50%
    expect($nudge['is_completed'])->toBeFalse();
    $component->assertSee('Thêm 1 sản phẩm nữa để được GIẢM 5% toàn đơn!');
    $component->assertSee('THÊM 1 SP → GIẢM 5%');
});

test('Challenge 2.2: Tiered Quantity Nudge with 3 items when tier requires 4 items (Step 2: 4 items -> 10%)', function () {
    PromotionRule::create([
        'name' => 'Chiết Khấu Số Lượng Lớn',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
        'discount_value' => 5.0,
        'conditions' => [
            'tiered_steps' => [
                ['min_qty' => 2, 'discount_percent' => 5],
                ['min_qty' => 4, 'discount_percent' => 10],
            ],
        ],
        'priority' => 1,
        'is_active' => true,
    ]);

    // 3 items in cart
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 3,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->not->toBeNull();
    expect($nudge['type'])->toBe('tiered_quantity');
    expect($nudge['gap_quantity'])->toBe(1); // 4 - 3 = 1
    expect($nudge['progress_percent'])->toBe(75.0); // 3 / 4 = 75%
    expect($nudge['is_completed'])->toBeFalse();
    $component->assertSee('Thêm 1 sản phẩm nữa để được GIẢM 10% toàn đơn!');
    $component->assertSee('THÊM 1 SP → GIẢM 10%');
});

// =========================================================================
// CHALLENGE 3: 1-Click Available Coupons Tray
// (Valid, Expired, Unmet Min Subtotal, Per-Customer Limit Reached)
// =========================================================================

test('Challenge 3.1: 1-Click Apply for Valid Coupon applies successfully and syncs session', function () {
    PromotionRule::create([
        'name' => 'Mã Khuyến Mãi Hợp Lệ 10%',
        'code' => 'VALID10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2, // 500,000₫
        ],
    ]);

    $component = Livewire::test(CartDrawer::class)
        ->call('applyCoupon', 'VALID10')
        ->assertSet('appliedCouponCode', 'VALID10')
        ->assertSet('couponError', null)
        ->assertSet('totalDiscount', 50000.0) // 10% of 500,000 = 50,000
        ->assertDispatched('coupon-applied')
        ->assertDispatched('toast');

    expect(Session::get('coupon'))->toBe('VALID10');
});

test('Challenge 3.2: 1-Click Apply for Expired Coupon is rejected with error', function () {
    PromotionRule::create([
        'name' => 'Mã Khuyến Mãi Hết Hạn',
        'code' => 'EXPIRED20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'ends_at' => now()->subDay(),
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class)
        ->call('applyCoupon', 'EXPIRED20')
        ->assertSet('appliedCouponCode', null)
        ->assertSet('couponError', 'Mã giảm giá [EXPIRED20] không tồn tại hoặc đã hết hạn.')
        ->assertDispatched('toast', message: 'Mã giảm giá [EXPIRED20] không tồn tại hoặc đã hết hạn.', type: 'error');

    expect(Session::has('coupon'))->toBeFalse();
});

test('Challenge 3.3: 1-Click Apply for Coupon with Unmet Min Subtotal is rejected with informative gap message', function () {
    PromotionRule::create([
        'name' => 'Đơn Hàng Lớn 1 Triệu',
        'code' => 'MIN1M',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 100000.0,
        'min_order_amount' => 1000000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1, // 250,000₫ < 1,000,000₫ (gap 750,000₫)
        ],
    ]);

    $component = Livewire::test(CartDrawer::class)
        ->call('applyCoupon', 'MIN1M')
        ->assertSet('appliedCouponCode', null)
        ->assertSee('yêu cầu đơn tối thiểu 1.000.000₫ (Cần thêm 750.000₫).');

    expect(Session::has('coupon'))->toBeFalse();
});

test('Challenge 3.4: 1-Click Apply for Coupon with Per-Customer Limit Reached is rejected', function () {
    $customer = Customer::create([
        'name' => 'Khách Hàng Thân Thiết',
        'email' => 'vip@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $rule = PromotionRule::create([
        'name' => 'Mã 1 Lần / Khách Hàng',
        'code' => 'ONCE10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'usage_limit_per_user' => 1,
        'priority' => 1,
        'is_active' => true,
    ]);

    // Record existing usage for this customer
    PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'customer_id' => $customer->id,
        'email' => $customer->email,
        'discount_amount' => 25000.0,
        'used_at' => now(),
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $this->actingAs($customer, 'customer');

    $component = Livewire::test(CartDrawer::class)
        ->call('applyCoupon', 'ONCE10')
        ->assertSet('appliedCouponCode', null)
        ->assertSee('không đủ điều kiện áp dụng cho đơn hàng hiện tại.');

    expect(Session::has('coupon'))->toBeFalse();
});

// =========================================================================
// CHALLENGE 4: Livewire Event Bus (cart-updated instant re-render of discounts & nudge)
// =========================================================================

test('Challenge 4: Dispatching cart-updated instantly re-renders subtotal, discounts, and smart nudge bar', function () {
    // Freeship threshold 500,000₫
    PromotionRule::create([
        'name' => 'Freeship 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // 10% Automatic Cart Discount
    PromotionRule::create([
        'name' => 'Giảm 10% Đơn Hàng',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'priority' => 2,
        'is_active' => true,
    ]);

    // Initial state: 1 item (250k)
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);

    // Assert initial state before event:
    // Subtotal: 250,000₫, 10% item discount = 25,000₫, Nudge: Cần thêm 250.000₫
    $component->assertSet('subtotal', 250000.0)
        ->assertSet('totalDiscount', 25000.0)
        ->assertSee('Mua thêm 250.000₫ để nhận FREESHIP');

    // Mutate cart storage to 2 items (500k)
    app(CartService::class)->update($this->productA->id, null, 2);

    // Dispatch cart-updated event
    $component->dispatch('cart-updated');

    // Assert instant re-rendered state:
    // Subtotal: 500,000₫
    // Item discount: 50,000₫ (10%)
    // Shipping discount: 30,000₫ (Free Shipping unlocked)
    // Total discount: 50,000 + 30,000 = 80,000₫
    // Net total: 500,000 + 30,000 - 80,000 = 450,000₫
    $component->assertSet('subtotal', 500000.0)
        ->assertSet('totalDiscount', 80000.0)
        ->assertSet('netTotal', 450000.0)
        ->assertSee('đủ điều kiện Freeship');
});
