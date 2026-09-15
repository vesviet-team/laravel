<?php

namespace Tests\Feature\Storefront;

use App\Actions\ProcessCheckoutAction;
use App\Events\OrderPlaced;
use App\Livewire\CartDrawer;
use App\Livewire\CouponInput;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromotionRule;
use App\Models\PromotionUsage;
use App\Services\CartService;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([OrderPlaced::class]);
    Cache::flush();
    Session::flush();

    $this->category = Category::create([
        'name' => 'Nội Thất Scandinavian',
        'slug' => 'scandinavian',
        'is_active' => true,
    ]);

    // Regular product (1,000,000 VND)
    $this->productA = Product::create([
        'name' => 'Bàn Ăn Gỗ Sồi Bắc Âu',
        'slug' => 'ban-an-go-soi-bac-au',
        'sku' => 'TBL-OAK-01',
        'price' => 1000000,
        'stock' => 50,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => true,
    ]);

    // Secondary product (500,000 VND)
    $this->productB = Product::create([
        'name' => 'Ghế Thư Giãn Armchair',
        'slug' => 'ghe-thu-gian-armchair',
        'sku' => 'CHR-ARM-01',
        'price' => 500000,
        'stock' => 30,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => false,
    ]);

    // Product with variants
    $this->productWithVariants = Product::create([
        'name' => 'Đèn Thả Trần Phong Cách Bắc Âu',
        'slug' => 'den-tha-tran-bac-au',
        'sku' => 'LGT-PEN-01',
        'price' => 800000,
        'stock' => 20,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => false,
    ]);

    $this->variantSmall = ProductVariant::create([
        'product_id' => $this->productWithVariants->id,
        'name' => 'Size S - 30cm',
        'sku' => 'LGT-PEN-01-S',
        'price' => 600000,
        'stock' => 10,
    ]);

    $this->variantLarge = ProductVariant::create([
        'product_id' => $this->productWithVariants->id,
        'name' => 'Size L - 50cm',
        'sku' => 'LGT-PEN-01-L',
        'price' => 900000,
        'stock' => 10,
    ]);
});

// =========================================================================
// PILLAR 1: Catalog Pricing Consistency Across Full Funnel
// (Product Card == Product Detail == Cart Item Unit Price == Checkout Line Price)
// =========================================================================

describe('Pillar 1: Catalog Pricing Consistency Across Full Funnel', function () {

    test('Vector 1.1: Base standard price consistency (No catalog promotion)', function () {
        $expectedPrice = 1000000.0;

        // 1. Product Card
        $cardView = $this->blade('<x-product-card :product="$product" />', ['product' => $this->productA]);
        $cardView->assertSee('1.000.000₫')
            ->assertDontSee('% PROMO')
            ->assertDontSee('line-through');

        // 2. Product Detail Page
        $detailResponse = $this->get(route('products.show', $this->productA->slug));
        $detailResponse->assertSuccessful()
            ->assertSee('1.000.000₫')
            ->assertDontSee('line-through')
            ->assertSee('"price": "1000000"', false);

        // 3. Cart Service Item Unit Price
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 2);
        $items = $cartService->getCartItemsDetails();
        expect($items[0]['price'])->toBe($expectedPrice);
        expect($items[0]['original_price'])->toBe($expectedPrice);
        expect($items[0]['subtotal'])->toBe(2000000.0);

        // 4. Checkout Page Line Item Price
        $checkoutResponse = $this->get(route('checkout.index'));
        $checkoutResponse->assertSuccessful()
            ->assertSee('2.000.000₫'); // Line subtotal for 2x 1.000.000₫
    });

    test('Vector 1.2: Percentage Catalog Promotion (15% off) consistency across all touchpoints', function () {
        // Create 15% Catalog Promotion Rule on Scandinavian Category
        PromotionRule::create([
            'name' => 'Ưu Đãi Scandinavian 15%',
            'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 15.0,
            'conditions' => [
                'category_ids' => [$this->category->id],
            ],
            'priority' => 1,
            'is_active' => true,
        ]);

        $originalPrice = 1000000.0;
        $expectedPromotedPrice = 850000.0; // 1M - 15% = 850K

        // 1. Product Card
        $cardView = $this->blade('<x-product-card :product="$product" />', ['product' => $this->productA]);
        $cardView->assertSee('-15% PROMO')
            ->assertSee('850.000₫')
            ->assertSee('1.000.000₫')
            ->assertSee('line-through');

        // 2. Product Detail Page
        $detailResponse = $this->get(route('products.show', $this->productA->slug));
        $detailResponse->assertSuccessful()
            ->assertSee('-15% PROMO')
            ->assertSee('CTKM: Ưu Đãi Scandinavian 15%')
            ->assertSee('850.000₫')
            ->assertSee('1.000.000₫')
            ->assertSee('Tiết kiệm: 150.000₫')
            ->assertSee('"price": "850000"', false);

        // 3. Cart Service Unit Price
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 3);
        $items = $cartService->getCartItemsDetails();
        expect($items[0]['price'])->toBe($expectedPromotedPrice);
        expect($items[0]['original_price'])->toBe($originalPrice);
        expect($items[0]['is_catalog_promoted'])->toBeTrue();
        expect($items[0]['subtotal'])->toBe(2550000.0); // 3 * 850K

        // 4. Checkout Line Item & ProcessCheckoutAction OrderItem Persistence
        $checkoutResponse = $this->get(route('checkout.index'));
        $checkoutResponse->assertSuccessful()
            ->assertSee('2.550.000₫');

        $checkoutAction = app(ProcessCheckoutAction::class);
        $order = $checkoutAction->execute([
            'customer_name' => 'Trần Văn Bình',
            'phone' => '0908888999',
            'email' => 'binh.tran@example.com',
            'address' => '456 Lê Duẩn',
            'city' => 'Hồ Chí Minh',
            'district' => 'Quận 1',
            'ward' => 'Bến Nghé',
            'payment_method' => 'cod',
        ]);

        expect($order->subtotal)->toBe(2550000);
        $orderItem = OrderItem::where('order_id', $order->id)->first();
        expect((float) $orderItem->price_at_purchase)->toBe($expectedPromotedPrice);
        expect((float) $orderItem->subtotal)->toBe(2550000.0);
    });

    test('Vector 1.3: Percentage with Cap Catalog Promotion (30% max 200,000đ) consistency', function () {
        // 30% of 1,000,000 = 300,000, but capped at 200,000 -> Promoted price: 800,000₫
        PromotionRule::create([
            'name' => 'Giảm 30% Tối Đa 200K',
            'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 30.0,
            'max_discount_amount' => 200000.0,
            'conditions' => [
                'product_ids' => [$this->productA->id],
            ],
            'priority' => 1,
            'is_active' => true,
        ]);

        $expectedPromotedPrice = 800000.0;

        // 1. Product Card
        $cardView = $this->blade('<x-product-card :product="$product" />', ['product' => $this->productA]);
        $cardView->assertSee('800.000₫')
            ->assertSee('1.000.000₫');

        // 2. Product Detail
        $detailResponse = $this->get(route('products.show', $this->productA->slug));
        $detailResponse->assertSuccessful()
            ->assertSee('800.000₫')
            ->assertSee('1.000.000₫')
            ->assertSee('Tiết kiệm: 200.000₫')
            ->assertSee('"price": "800000"', false);

        // 3. Cart Service Unit Price
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1);
        $items = $cartService->getCartItemsDetails();
        expect($items[0]['price'])->toBe($expectedPromotedPrice);
    });

    test('Vector 1.4: Fixed Amount Catalog Promotion (Deduct 250,000đ) consistency', function () {
        PromotionRule::create([
            'name' => 'Trừ Trực Tiếp 250K',
            'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 250000.0,
            'conditions' => [
                'product_ids' => [$this->productA->id],
            ],
            'priority' => 1,
            'is_active' => true,
        ]);

        $expectedPromotedPrice = 750000.0;

        // 1. Card
        $cardView = $this->blade('<x-product-card :product="$product" />', ['product' => $this->productA]);
        $cardView->assertSee('750.000₫')
            ->assertSee('1.000.000₫');

        // 2. Detail
        $detailResponse = $this->get(route('products.show', $this->productA->slug));
        $detailResponse->assertSuccessful()
            ->assertSee('750.000₫')
            ->assertSee('1.000.000₫')
            ->assertSee('Tiết kiệm: 250.000₫');

        // 3. Cart Service Unit Price
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1);
        $items = $cartService->getCartItemsDetails();
        expect($items[0]['price'])->toBe($expectedPromotedPrice);
    });

    test('Vector 1.5: Flash Sale price strictly takes precedence over catalog promo rules', function () {
        // 1. Catalog Rule offers 15% off (850,000₫)
        PromotionRule::create([
            'name' => 'Catalog Rule 15%',
            'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 15.0,
            'conditions' => ['category_ids' => [$this->category->id]],
            'priority' => 1,
            'is_active' => true,
        ]);

        // 2. Active Flash Sale offers deep discount (650,000₫)
        $flashSale = FlashSale::create([
            'name' => 'Flash Sale Midnight',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
            'status' => 'active',
        ]);

        FlashSaleItem::create([
            'flash_sale_id' => $flashSale->id,
            'product_id' => $this->productA->id,
            'price' => 650000.0, // Flash sale price
            'quantity' => 10,
            'sold_quantity' => 0,
        ]);

        $flashSalePrice = 650000.0;

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1);
        $items = $cartService->getCartItemsDetails();

        // Flash sale price MUST override catalog promo
        expect($items[0]['price'])->toBe($flashSalePrice);
        expect($items[0]['is_flash_sale'])->toBeTrue();
        expect($items[0]['is_catalog_promoted'])->toBeFalse();
    });

    test('Vector 1.6: Product Variant price resolution in Cart and Checkout', function () {
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        // Add Size S (600K) and Size L (900K)
        $cartService->add($this->productWithVariants->id, $this->variantSmall->id, 1);
        $cartService->add($this->productWithVariants->id, $this->variantLarge->id, 2);

        $items = $cartService->getCartItemsDetails();
        expect($items)->toHaveCount(2);
        expect($items[0]['price'])->toBe(600000.0);
        expect($items[0]['variant_name'])->toBe('Size S - 30cm');
        expect($items[1]['price'])->toBe(900000.0);
        expect($items[1]['variant_name'])->toBe('Size L - 50cm');
        expect($items[1]['subtotal'])->toBe(1800000.0);

        expect($cartService->calculateTotal())->toBe(2400000.0);
    });
});

// =========================================================================
// PILLAR 2: Livewire CartDrawer Reactivity & Dynamic Calculations
// =========================================================================

describe('Pillar 2: Livewire CartDrawer Reactivity & Nudge Dynamic Upgrades', function () {

    test('Vector 2.1: Quantity increment/decrement instantly updates subtotal, netTotal and Nudge bar without reload', function () {
        // Freeship threshold 1,500,000 VND
        PromotionRule::create([
            'name' => 'Freeship Đơn Từ 1.5 Triệu',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
            'discount_value' => 0.0,
            'min_order_amount' => 1500000.0,
            'priority' => 1,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productB->id, null, 1); // 1x 500k = 500k

        $component = Livewire::test(CartDrawer::class);

        // Step 1: Initial (500k / 1.5M = 33.3%, gap 1.000.000₫, shipping fee 30.000₫ -> netTotal = 530.000₫)
        expect($component->get('subtotal'))->toBe(500000.0);
        expect($component->get('netTotal'))->toBe(530000.0);
        $nudge1 = $component->get('smartNudge');
        expect($nudge1['progress_percent'])->toBe(33.3);
        expect($nudge1['gap_amount'])->toBe(1000000.0);
        expect($nudge1['is_completed'])->toBeFalse();
        $component->assertSee('Mua thêm 1.000.000₫ để nhận FREESHIP toàn quốc!');
        $component->assertSee('CÒN THIẾU 1.000.000₫');

        // Step 2: Increase quantity to 2 (1.000.000₫ / 1.5M = 66.7%, gap 500.000₫ -> netTotal = 1.030.000₫)
        $component->call('updateQuantity', $this->productB->id, null, 2, $cartService);
        expect($component->get('subtotal'))->toBe(1000000.0);
        expect($component->get('netTotal'))->toBe(1030000.0);
        $nudge2 = $component->get('smartNudge');
        expect($nudge2['progress_percent'])->toBe(66.7);
        expect($nudge2['gap_amount'])->toBe(500000.0);
        expect($nudge2['is_completed'])->toBeFalse();
        $component->assertSee('Mua thêm 500.000₫ để nhận FREESHIP toàn quốc!');
        $component->assertSee('CÒN THIẾU 500.000₫');

        // Step 3: Increase quantity to 3 (1.500.000₫ / 1.5M = 100%, Freeship waived 30k -> netTotal = 1.500.000₫)
        $component->call('updateQuantity', $this->productB->id, null, 3, $cartService);
        expect($component->get('subtotal'))->toBe(1500000.0);
        expect($component->get('netTotal'))->toBe(1500000.0);
        $nudge3 = $component->get('smartNudge');
        expect($nudge3['progress_percent'])->toBe(100.0);
        expect($nudge3['gap_amount'])->toBe(0.0);
        expect($nudge3['is_completed'])->toBeTrue();
        $component->assertSee('Tuyệt vời! Đơn hàng của bạn đã đủ điều kiện Freeship toàn quốc!');
        $component->assertSee('FREESHIP ĐẠT 100%');

        // Step 4: Decrease quantity back to 1
        $component->call('updateQuantity', $this->productB->id, null, 1, $cartService);
        expect($component->get('subtotal'))->toBe(500000.0);
        expect($component->get('netTotal'))->toBe(530000.0);
        $nudge4 = $component->get('smartNudge');
        expect($nudge4['is_completed'])->toBeFalse();
        expect($nudge4['gap_amount'])->toBe(1000000.0);
    });

    test('Vector 2.2: Tiered Quantity dynamic step nudge progression (1 sp -> 2 sp -> 4 sp -> 6 sp)', function () {
        PromotionRule::create([
            'name' => 'Chiết Khấu Số Lượng Bậc Thang',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
            'discount_value' => 5.0,
            'conditions' => [
                'tiered_steps' => [
                    ['min_qty' => 2, 'discount_percent' => 5],
                    ['min_qty' => 4, 'discount_percent' => 10],
                    ['min_qty' => 6, 'discount_percent' => 15],
                ],
            ],
            'priority' => 1,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productB->id, null, 1);

        $component = Livewire::test(CartDrawer::class);

        // 1 qty -> Next step: 2 qty (Need 1 more for 5%)
        $nudge = $component->get('smartNudge');
        expect($nudge['type'])->toBe('tiered_quantity');
        expect($nudge['gap_quantity'])->toBe(1);
        expect($nudge['badge'])->toBe('THÊM 1 SP → GIẢM 5%');

        // Update to 2 qty -> Next step: 4 qty (Need 2 more for 10%)
        $component->call('updateQuantity', $this->productB->id, null, 2, $cartService);
        $nudge = $component->get('smartNudge');
        expect($nudge['gap_quantity'])->toBe(2);
        expect($nudge['badge'])->toBe('THÊM 2 SP → GIẢM 10%');

        // Update to 5 qty -> Next step: 6 qty (Need 1 more for 15%)
        $component->call('updateQuantity', $this->productB->id, null, 5, $cartService);
        $nudge = $component->get('smartNudge');
        expect($nudge['gap_quantity'])->toBe(1);
        expect($nudge['badge'])->toBe('THÊM 1 SP → GIẢM 15%');

        // Update to 6 qty -> Max tier reached (15%)
        $component->call('updateQuantity', $this->productB->id, null, 6, $cartService);
        $nudge = $component->get('smartNudge');
        expect($nudge['is_completed'])->toBeTrue();
        expect($nudge['badge'])->toBe('ĐẠT MỨC GIẢM 15%');
    });

    test('Vector 2.3: 1-Click Available Coupons Tray: eligibility gating, apply, and removal cycle', function () {
        // Voucher 1: Min 1,000,000 VND -> 100K off
        PromotionRule::create([
            'name' => 'Voucher 100K Đơn 1 Triệu',
            'code' => 'VOUCHER100',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 100000.0,
            'min_order_amount' => 1000000.0,
            'priority' => 1,
            'is_active' => true,
        ]);

        // Voucher 2: Min 2,000,000 VND -> 200K off
        PromotionRule::create([
            'name' => 'Voucher 200K Đơn 2 Triệu',
            'code' => 'VOUCHER200',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 200000.0,
            'min_order_amount' => 2000000.0,
            'priority' => 2,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1); // 1x 1.000.000₫

        $component = Livewire::test(CartDrawer::class);

        // Check available coupons collection
        $availableCoupons = $component->get('availableCoupons');
        expect($availableCoupons)->toHaveCount(2);

        // Voucher 100: eligible
        $coupon100 = $availableCoupons->firstWhere('code', 'VOUCHER100');
        expect($coupon100->is_eligible)->toBeTrue();
        expect($coupon100->ineligible_reason)->toBeNull();

        // Voucher 200: ineligible with actionable near-miss message
        $coupon200 = $availableCoupons->firstWhere('code', 'VOUCHER200');
        expect($coupon200->is_eligible)->toBeFalse();
        expect($coupon200->ineligible_reason)->toBe('Mua thêm 1.000.000₫');

        // Apply Voucher 100 via 1-Click
        // Subtotal: 1.000.000, Discount: 100.000, Estimated Shipping: 30.000 -> Net: 930.000₫
        $component->call('applyCoupon', 'VOUCHER100');
        expect($component->get('appliedCouponCode'))->toBe('VOUCHER100');
        expect($component->get('couponSuccess'))->toContain('Đã áp dụng mã [VOUCHER100]');
        expect($component->get('totalDiscount'))->toBe(100000.0);
        expect($component->get('netTotal'))->toBe(930000.0);
        $component->assertDispatched('coupon-applied');

        // Attempting to apply ineligible Voucher 200 is rejected
        $component->call('applyCoupon', 'VOUCHER200');
        expect($component->get('couponError'))->toContain('yêu cầu đơn tối thiểu 2.000.000₫');
        // Original coupon remains intact
        expect($component->get('appliedCouponCode'))->toBe('VOUCHER100');

        // Remove Coupon
        $component->call('removeCoupon');
        expect($component->get('appliedCouponCode'))->toBeNull();
        expect($component->get('totalDiscount'))->toBe(0.0);
        expect($component->get('netTotal'))->toBe(1030000.0);
        $component->assertDispatched('coupon-removed');
    });

    test('Vector 2.4: Removing item and clearing cart handles reactive state and empty state safely', function () {
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1);
        $cartService->add($this->productB->id, null, 2);

        $component = Livewire::test(CartDrawer::class);
        expect($component->get('totalQuantity'))->toBe(3);
        expect($component->get('subtotal'))->toBe(2000000.0);

        // Remove Product B
        $component->call('removeItem', $this->productB->id, null, $cartService);
        expect($component->get('totalQuantity'))->toBe(1);
        expect($component->get('subtotal'))->toBe(1000000.0);

        // Clear entire cart
        $component->call('clearCart', $cartService);
        expect($component->get('cartItems'))->toBeEmpty();
        expect($component->get('subtotal'))->toBe(0.0);
        expect($component->get('totalQuantity'))->toBe(0);
        expect($component->get('smartNudge'))->toBeNull();
        $component->assertSee('Giỏ hàng của bạn đang trống');
    });
});

// =========================================================================
// PILLAR 3: Checkout Breakdown Transparency & Stacked Discounts
// =========================================================================

describe('Pillar 3: Checkout Breakdown Transparency & Stacked Discounts', function () {

    test('Vector 3.1: Stacked discounts (Cart Rule 10% + Coupon 50K + Freeship) display as separate labeled line items', function () {
        // 1. Automatic Cart Rule: 10% on whole cart
        $autoRule = PromotionRule::create([
            'name' => 'Khuyến Mãi Mùa Thu 10%',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 10.0,
            'priority' => 1,
            'is_active' => true,
        ]);

        // 2. Coupon Rule: Fixed 50,000 VND
        $couponRule = PromotionRule::create([
            'name' => 'Voucher Giảm 50K',
            'code' => 'SALE50K',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 50000.0,
            'priority' => 2,
            'is_active' => true,
        ]);

        // 3. Free Shipping Rule
        $freeshipRule = PromotionRule::create([
            'name' => 'Miễn Phí Vận Chuyển 1M',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
            'discount_value' => 0.0,
            'min_order_amount' => 1000000.0,
            'priority' => 3,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1); // 1.000.000₫
        $cartService->add($this->productB->id, null, 1); // 500.000₫ -> Subtotal = 1.500.000₫

        Session::put('coupon', 'SALE50K');

        $engine = app(PromotionEngine::class);
        $breakdown = $engine->calculateCartDiscounts(
            subtotal: 1500000.0,
            cartItems: $cartService->getCartItemsDetails(),
            couponCode: 'SALE50K',
            shippingFee: 30000.0
        );

        // Math verification:
        // Subtotal: 1.500.000₫
        // Auto Rule 10%: 150.000₫
        // Coupon Rule: 50.000₫
        // Shipping Discount: 30.000₫ (Waived from 30.000₫ to 0₫)
        // Total Item Discounts (Auto + Coupon): 200.000₫
        // Total Discount: 230.000₫
        // Final Total: 1.500.000 - 150.000 - 50.000 = 1.300.000₫
        expect($breakdown->subtotal)->toBe(1500000.0);
        expect($breakdown->itemDiscounts)->toBe(200000.0);
        expect($breakdown->couponDiscount)->toBe(50000.0);
        expect($breakdown->shippingDiscount)->toBe(30000.0);
        expect($breakdown->finalShippingFee)->toBe(0.0);
        expect($breakdown->totalDiscount)->toBe(230000.0);
        expect($breakdown->finalTotal)->toBe(1300000.0);

        // Verify applied rules itemization (each rule separate)
        expect($breakdown->appliedRules)->toHaveCount(3);
        $ruleNames = array_map(fn ($r) => $r->ruleName, $breakdown->appliedRules);
        expect($ruleNames)->toContain('Khuyến Mãi Mùa Thu 10%');
        expect($ruleNames)->toContain('Voucher Giảm 50K');
        expect($ruleNames)->toContain('Miễn Phí Vận Chuyển 1M');

        // Render Checkout Page and assert distinct itemized line items
        $response = $this->get(route('checkout.index'));
        $response->assertSuccessful();
        $response->assertSee('1.500.000₫'); // Subtotal
        $response->assertSee('Khuyến Mãi Mùa Thu 10%');
        $response->assertSee('-150.000₫');
        $response->assertSee('Voucher Giảm 50K');
        $response->assertSee('-50.000₫');
        $response->assertSee('Mã coupon');
        $response->assertSee('Miễn phí'); // Shipping
        // Pre-shipping page stage: item + coupon discounts (150k + 50k).
        // The 30k shipping waiver applies once an address sets the fee.
        $response->assertSee('-200.000₫'); // Total discount
        $response->assertSee('1.300.000₫'); // Final total
    });

    test('Vector 3.2: Livewire CouponInput component on checkout page applies coupon and sets session', function () {
        PromotionRule::create([
            'name' => 'Voucher Giảm 100K',
            'code' => 'VIP100',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 100000.0,
            'priority' => 1,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1); // 1.000.000₫

        $component = Livewire::test(CouponInput::class, ['subtotal' => 1000000.0]);

        $component->set('couponCode', 'vip100');
        $component->call('applyCoupon', app(CartService::class), app(PromotionEngine::class));

        expect($component->get('discount'))->toBe(100000.0);
        expect($component->get('couponApplied'))->toBe('VIP100');
        expect(Session::get('coupon'))->toBe('VIP100');

        // Test removal
        $component->call('removeCoupon');
        expect($component->get('discount'))->toBe(0.0);
        expect($component->get('couponApplied'))->toBeNull();
        expect(Session::get('coupon'))->toBeNull();
    });

    test('Vector 3.3: Buy X Get Y promotion deduction in applied promotions list', function () {
        // Buy Product A -> Get Product B free
        PromotionRule::create([
            'name' => 'Mua Bàn Tặng Ghế',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'discount_value' => 0.0,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => $this->productA->id,
                    'buy_quantity' => 1,
                    'get_product_id' => $this->productB->id,
                    'get_quantity' => 1,
                    'is_free' => true,
                    'max_rewards' => 1,
                ],
            ],
            'priority' => 1,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1); // 1.000.000₫
        $cartService->add($this->productB->id, null, 1); // 500.000₫

        $response = $this->get(route('checkout.index'));
        $response->assertSuccessful()
            ->assertSee('Mua Bàn Tặng Ghế')
            ->assertSee('-500.000₫');
    });

    test('Vector 3.4: Complete checkout execution persists accurate order financial breakdown and usage rows', function () {
        $autoRule = PromotionRule::create([
            'name' => 'Khuyến Mãi Mùa Thu 10%',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 10.0,
            'priority' => 1,
            'is_active' => true,
        ]);

        $couponRule = PromotionRule::create([
            'name' => 'Voucher Giảm 50K',
            'code' => 'VOUCHER50K',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
            'discount_value' => 50000.0,
            'priority' => 2,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1); // 1.000.000₫

        $checkoutAction = app(ProcessCheckoutAction::class);
        $order = $checkoutAction->execute([
            'customer_name' => 'Hoàng Minh Tuấn',
            'phone' => '0912345678',
            'email' => 'tuan.hoang@example.com',
            'address' => '789 Nguyễn Huệ',
            'city' => 'Hồ Chí Minh',
            'district' => 'Quận 1',
            'ward' => 'Bến Nghé',
            'payment_method' => 'cod',
        ], 'VOUCHER50K');

        // Subtotal = 1.000.000, 10% auto = 100.000, 50k coupon = 50.000 -> Total discount = 150.000, Total = 850.000
        expect($order->subtotal)->toBe(1000000);
        expect($order->discount_amount)->toBe(150000);
        expect($order->total_amount)->toBe(850000);

        // Verify usage tracking
        $usages = PromotionUsage::where('order_id', $order->id)->get();
        expect($usages)->toHaveCount(2);

        $autoUsage = $usages->firstWhere('promotion_rule_id', $autoRule->id);
        expect($autoUsage)->not->toBeNull();
        expect((float) $autoUsage->discount_amount)->toBe(100000.0);

        $couponUsage = $usages->firstWhere('promotion_rule_id', $couponRule->id);
        expect($couponUsage)->not->toBeNull();
        expect((float) $couponUsage->discount_amount)->toBe(50000.0);
    });
});

// =========================================================================
// PILLAR 4: Adversarial Stress Vectors & Edge Case Mining
// =========================================================================

describe('Pillar 4: Adversarial Stress Vectors & Edge Case Mining', function () {

    test('Vector 4.1: Extreme cart subtotal (100,000,000₫) with percentage discount and upper cap', function () {
        PromotionRule::create([
            'name' => 'Mega Sale 20% Capped 2M',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 20.0,
            'max_discount_amount' => 2000000.0, // 2 Million max
            'priority' => 1,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 99); // qty capped at 99 (CartService business rule)

        $component = Livewire::test(CartDrawer::class);
        expect($component->get('subtotal'))->toBe(99000000.0);
        expect($component->get('totalDiscount'))->toBe(2000000.0);
        // Subtotal (99M) - Discount (2M) + Est Shipping (30K) = 97,030,000d
        expect($component->get('netTotal'))->toBe(97030000.0);
    });

    test('Vector 4.2: Zero or invalid quantity mutations are rejected without corrupting cart state', function () {
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1);

        $component = Livewire::test(CartDrawer::class);

        // Attempt mutation to 0
        $component->call('updateQuantity', $this->productA->id, null, 0, $cartService);
        expect($component->get('totalQuantity'))->toBe(1);

        // Attempt mutation to -5
        $component->call('updateQuantity', $this->productA->id, null, -5, $cartService);
        expect($component->get('totalQuantity'))->toBe(1);
    });

    test('Vector 4.3: Customer Tier gating: VIP-only coupon rejection for guest customer', function () {
        PromotionRule::create([
            'name' => 'VIP Gold Exclusive 25%',
            'code' => 'VIPGOLD25',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 25.0,
            'target_customer_tier' => PromotionRule::TIER_GOLD,
            'priority' => 1,
            'is_active' => true,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1);

        $component = Livewire::test(CartDrawer::class);

        // Guest customer attempts to apply VIP coupon
        $component->call('applyCoupon', 'VIPGOLD25');
        expect($component->get('couponError'))->toContain('không đủ điều kiện áp dụng');
        expect($component->get('appliedCouponCode'))->toBeNull();
    });

    test('Vector 4.4: Inactive and Expired coupons produce user-safe clear error messages', function () {
        // Expired rule
        PromotionRule::create([
            'name' => 'Expired Promo',
            'code' => 'EXPIRED2025',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 20.0,
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subMonth(),
            'priority' => 1,
            'is_active' => true,
        ]);

        // Inactive rule
        PromotionRule::create([
            'name' => 'Inactive Promo',
            'code' => 'INACTIVE2026',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 20.0,
            'priority' => 1,
            'is_active' => false,
        ]);

        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        $cartService->add($this->productA->id, null, 1);

        $component = Livewire::test(CartDrawer::class);

        $component->call('applyCoupon', 'EXPIRED2025');
        expect($component->get('couponError'))->toContain('không tồn tại hoặc đã hết hạn');

        $component->call('applyCoupon', 'INACTIVE2026');
        expect($component->get('couponError'))->toContain('không tồn tại hoặc đã hết hạn');

        $component->call('applyCoupon', 'NONEXISTENT');
        expect($component->get('couponError'))->toContain('không tồn tại hoặc đã hết hạn');
    });
});
