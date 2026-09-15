<?php

namespace Tests\Feature\Storefront;

use App\Livewire\CartDrawer;
use App\Livewire\CouponInput;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear catalog rules cache
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
        'price' => 500000,
        'stock' => 20,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => false,
    ]);

    $this->productB = Product::create([
        'name' => 'Bàn Trà Tối Giản',
        'slug' => 'ban-tra-toi-gian',
        'sku' => 'TBL-001',
        'price' => 500000,
        'stock' => 15,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => true,
    ]);
});

// ==========================================
// 1. Product Card Component Tests
// ==========================================

test('product card renders promo badge and strike-through price when catalog promotion is active', function () {
    // Active 15% Catalog Rule on Category
    PromotionRule::create([
        'name' => 'Chiếu Sáng & Bàn Ghế 15%',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 15.0,
        'conditions' => [
            'category_ids' => [$this->category->id],
        ],
        'priority' => 1,
        'is_active' => true,
    ]);

    $view = $this->blade('<x-product-card :product="$product" />', [
        'product' => $this->productA,
    ]);

    // Original: 500.000₫, Promoted (15% off): 425.000₫
    $view->assertSee('-15% PROMO')
        ->assertSee('425.000₫')
        ->assertSee('500.000₫')
        ->assertSee('line-through');
});

test('product card renders standard price without promo badge when no catalog promotion applies', function () {
    $view = $this->blade('<x-product-card :product="$product" />', [
        'product' => $this->productA,
    ]);

    $view->assertSee('500.000₫')
        ->assertDontSee('% PROMO')
        ->assertDontSee('line-through');
});

test('product card renders both HOT badge and promo badge when featured product has active promotion', function () {
    PromotionRule::create([
        'name' => 'Giảm 20% Danh Mục',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'conditions' => [
            'category_ids' => [$this->category->id],
        ],
        'priority' => 1,
        'is_active' => true,
    ]);

    $view = $this->blade('<x-product-card :product="$product" />', [
        'product' => $this->productB, // is_featured = true
    ]);

    $view->assertSee('HOT')
        ->assertSee('-20% PROMO')
        ->assertSee('400.000₫')
        ->assertSee('500.000₫');
});

// ==========================================
// 2. Product Show Detail View Tests
// ==========================================

test('product show page displays promo strike price, campaign name tag, savings callout, and JSON-LD sync', function () {
    PromotionRule::create([
        'name' => 'Chiến Dịch Mùa Thu 10%',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'conditions' => [
            'category_ids' => [$this->category->id],
        ],
        'priority' => 1,
        'is_active' => true,
    ]);

    $response = $this->get(route('products.show', $this->productA->slug));

    $response->assertSuccessful();
    // Promoted price: 450.000₫, Original: 500.000₫, Savings: 50.000₫
    $response->assertSee('450.000₫')
        ->assertSee('500.000₫')
        ->assertSee('-10% PROMO')
        ->assertSee('CTKM: Chiến Dịch Mùa Thu 10%')
        ->assertSee('Tiết kiệm: 50.000₫')
        ->assertSee('"price": "450000"', false);
});

// ==========================================
// 3. Livewire CartDrawer Subsystem Tests
// ==========================================

test('cart drawer calculates real-time subtotal, discounts, and net total via PromotionEngine', function () {
    // 10% Automatic Cart Rule
    PromotionRule::create([
        'name' => 'Giảm 10% Toàn Cửa Hàng',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // 2 items = 1,000,000₫
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
        "{$this->productB->id}_0" => [
            'product_id' => $this->productB->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    Livewire::test(CartDrawer::class)
        ->assertSet('subtotal', 1000000.0)
        ->assertSet('totalDiscount', 100000.0)
        ->assertSet('netTotal', 930000.0) // 1M - 100k discount + 30k estimated shipping = 930.000₫
        ->assertSee('1.000.000₫')
        ->assertSee('-100.000₫');
});

test('calculates Free Shipping Smart Nudge threshold gap and progress percentage', function () {
    // Free Shipping on orders >= 1,000,000₫
    PromotionRule::create([
        'name' => 'Freeship Đơn Từ 1 Triệu',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 1000000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // Scenario 1: Subtotal = 500k -> 50% Progress, 500k Gap
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
    expect($nudge['type'])->toBe('free_shipping');
    expect($nudge['progress_percent'])->toBe(50.0);
    expect($nudge['gap_amount'])->toBe(500000.0);
    expect($nudge['is_completed'])->toBeFalse();
    $component->assertSee('Mua thêm 500.000₫ để nhận FREESHIP toàn quốc!');

    // Scenario 2: Subtotal = 1,000,000₫ -> 100% Progress
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
        "{$this->productB->id}_0" => [
            'product_id' => $this->productB->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $componentFull = Livewire::test(CartDrawer::class);
    $nudgeFull = $componentFull->get('smartNudge');

    expect($nudgeFull['progress_percent'])->toBe(100.0);
    expect($nudgeFull['is_completed'])->toBeTrue();
    $componentFull->assertSee('đủ điều kiện Freeship');
});

test('calculates Tiered Quantity discount upgrade gap in Smart Nudge', function () {
    // Tiered Rule: 2 items -> 5%, 4 items -> 10%
    PromotionRule::create([
        'name' => 'Chiết Khấu Mua Nhiều',
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
    expect($nudge['progress_percent'])->toBe(50.0);
    $component->assertSee('Thêm 1 sản phẩm nữa để được GIẢM 5%');
});

test('queries active coupon rules for 1-Click Available Coupons Tray', function () {
    $activeCoupon = PromotionRule::create([
        'name' => 'Chào Mừng Thành Viên Mới 10%',
        'code' => 'WELCOME10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    $expiredCoupon = PromotionRule::create([
        'name' => 'Mã Khuyến Mãi Hết Hạn',
        'code' => 'EXPIRED20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'ends_at' => now()->subDay(),
        'priority' => 2,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $availableCoupons = $component->get('availableCoupons');

    expect($availableCoupons)->toHaveCount(1);
    expect($availableCoupons->first()->code)->toBe('WELCOME10');
    $component->call('toggleCouponsTray')
        ->assertSee('WELCOME10')
        ->assertDontSee('EXPIRED20');
});

test('applies eligible coupon via 1-click apply action and synchronizes session', function () {
    PromotionRule::create([
        'name' => 'VIP Giảm 20%',
        'code' => 'VIP20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2, // 1,000,000₫
        ],
    ]);

    Livewire::test(CartDrawer::class)
        ->call('applyCoupon', 'VIP20')
        ->assertSet('appliedCouponCode', 'VIP20')
        ->assertSet('totalDiscount', 200000.0)
        ->assertDispatched('coupon-applied')
        ->assertDispatched('toast');

    expect(Session::get('coupon'))->toBe('VIP20');

    // Test removing coupon
    Livewire::test(CartDrawer::class)
        ->call('removeCoupon')
        ->assertSet('appliedCouponCode', null)
        ->assertSet('totalDiscount', 0.0)
        ->assertDispatched('coupon-removed');

    expect(Session::has('coupon'))->toBeFalse();
});

test('rejects coupon in drawer when minimum order requirement is not met', function () {
    PromotionRule::create([
        'name' => 'Đơn To Giảm 100K',
        'code' => 'BIG100K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 100000.0,
        'min_order_amount' => 2000000.0, // 2M required
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1, // 500,000₫ < 2,000,000₫
        ],
    ]);

    Livewire::test(CartDrawer::class)
        ->call('applyCoupon', 'BIG100K')
        ->assertSet('appliedCouponCode', null)
        ->assertSee('yêu cầu đơn tối thiểu 2.000.000₫');

    expect(Session::has('coupon'))->toBeFalse();
});

test('strictly isolates flash sale items from percentage cart promotions in drawer calculations', function () {
    $flashSale = FlashSale::create([
        'name' => 'Flash Sale Đêm Khuya',
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'is_active' => true,
    ]);

    FlashSaleItem::create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $this->productA->id,
        'price' => 400000, // Reduced from 500k
        'quantity' => 10,
        'sold_quantity' => 0,
    ]);

    // 10% Storewide Cart Rule
    PromotionRule::create([
        'name' => 'Ưu Đãi Toàn Sàn 10%',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'is_active' => true,
    ]);

    // Cart: 1 Flash Sale Product A (400k) + 1 Regular Product B (500k)
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
        "{$this->productB->id}_0" => [
            'product_id' => $this->productB->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    Livewire::test(CartDrawer::class)
        ->assertSet('subtotal', 900000.0) // 400k flash sale + 500k regular
        ->assertSet('totalDiscount', 50000.0); // 10% of 500k regular item only!
});

test('handles cart-updated and cart-cleared reactive events smoothly', function () {
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class)
        ->assertSet('totalQuantity', 1);

    // Update quantity via service
    app(CartService::class)->update($this->productA->id, null, 3);
    $component->dispatch('cart-updated')
        ->assertSet('totalQuantity', 3);

    // Clear cart
    app(CartService::class)->clear();
    $component->dispatch('cart-cleared')
        ->assertSet('cartItems', [])
        ->assertSee('Giỏ hàng của bạn đang trống');
});

// ==========================================
// 4. Storefront Checkout Breakdown Tests
// ==========================================

test('checkout view renders transparent financial breakdown of applied promotions', function () {
    PromotionRule::create([
        'name' => 'Combo Giảm 50K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 50000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    $cartData = [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2, // 1,000,000₫
        ],
    ];

    $response = $this->withSession(['cart' => $cartData])
        ->get(route('checkout.index'));

    $response->assertSuccessful();
    $response->assertSee('Tóm Tắt Đơn Hàng')
        ->assertSee('1.000.000₫') // Subtotal
        ->assertSee('Combo Giảm 50K')
        ->assertSee('-50.000₫') // Discount
        ->assertSee('950.000₫'); // Net total: 1,000,000 - 50,000 = 950,000₫
});

// ==========================================
// 5. CartService Catalog Promotion Hook Tests
// ==========================================

test('cart service calculates line subtotal using promoted price when catalog rule is active', function () {
    PromotionRule::create([
        'name' => 'Chiếu Sáng & Bàn Ghế 20%',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'conditions' => [
            'category_ids' => [$this->category->id],
        ],
        'priority' => 1,
        'is_active' => true,
    ]);

    $cartService = app(CartService::class);
    $cartService->add($this->productA->id, null, 2);

    $items = $cartService->getCartItemsDetails();

    expect($items)->toHaveCount(1);
    expect($items[0]['price'])->toBe(400000.0);
    expect($items[0]['original_price'])->toBe(500000.0);
    expect($items[0]['subtotal'])->toBe(800000.0);
    expect($items[0]['is_catalog_promoted'])->toBeTrue();
    expect($cartService->calculateTotal())->toBe(800000.0);
});

test('cart service isolates flash sale items from catalog promotion overrides', function () {
    $flashSale = FlashSale::create([
        'name' => 'Flash Sale Chớp Nhoáng',
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
        'is_active' => true,
    ]);

    FlashSaleItem::create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $this->productA->id,
        'price' => 350000,
        'quantity' => 10,
        'sold_quantity' => 0,
    ]);

    PromotionRule::create([
        'name' => 'Catalog Giảm 20%',
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'conditions' => ['category_ids' => [$this->category->id]],
        'is_active' => true,
    ]);

    $cartService = app(CartService::class);
    $cartService->add($this->productA->id, null, 1);

    $items = $cartService->getCartItemsDetails();

    expect($items[0]['price'])->toBe(350000.0);
    expect($items[0]['is_flash_sale'])->toBeTrue();
    expect($items[0]['is_catalog_promoted'])->toBeFalse();
});

// ==========================================
// 6. Livewire CouponInput Component Tests
// ==========================================

test('coupon input component applies coupon and dispatches coupon-applied event', function () {
    PromotionRule::create([
        'name' => 'Mã Khuyến Mãi 50K',
        'code' => 'SAVE50',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 50000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2, // 1,000,000₫
        ],
    ]);

    Livewire::test(CouponInput::class, ['subtotal' => 1000000.0])
        ->set('couponCode', 'SAVE50')
        ->call('applyCoupon')
        ->assertSet('couponApplied', 'SAVE50')
        ->assertSet('discount', 50000.0)
        ->assertDispatched('coupon-applied')
        ->assertSee('SAVE50')
        ->assertSee('đã áp dụng')
        ->assertSee('-50.000₫');

    expect(Session::get('coupon'))->toBe('SAVE50');

    // Test remove
    Livewire::test(CouponInput::class, ['subtotal' => 1000000.0])
        ->call('removeCoupon')
        ->assertSet('couponApplied', null)
        ->assertSet('discount', 0.0)
        ->assertDispatched('coupon-removed');

    expect(Session::has('coupon'))->toBeFalse();
});

test('coupon input displays error message on invalid or expired code', function () {
    Livewire::test(CouponInput::class, ['subtotal' => 500000.0])
        ->set('couponCode', 'NONEXISTENT')
        ->call('applyCoupon')
        ->assertSet('couponApplied', null)
        ->assertSee('không tồn tại hoặc đã hết hạn');
});

test('checkout view renders applied promotion discount for buy x get y promotions', function () {
    PromotionRule::create([
        'name' => 'Mua 2 Ghế Tặng Bàn Trà',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
        'discount_value' => 100.0,
        'conditions' => [
            'buy_product_id' => $this->productA->id,
            'buy_quantity' => 2,
            'get_product_id' => $this->productB->id,
            'get_quantity' => 1,
            'discount_percent' => 100,
        ],
        'priority' => 1,
        'is_active' => true,
    ]);

    $cartData = [
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
    ];

    $response = $this->withSession(['cart' => $cartData])
        ->get(route('checkout.index'));

    $response->assertSuccessful();
    $response->assertSee('Tóm Tắt Đơn Hàng')
        ->assertSee('1.500.000₫')
        ->assertSee('Mua 2 Ghế Tặng Bàn Trà')
        ->assertSee('-500.000₫')
        ->assertSee('1.000.000₫');
});
