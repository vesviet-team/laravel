<?php

namespace Tests\Feature\Storefront;

use App\Enums\OrderStatus;
use App\Livewire\CartDrawer;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->category = Category::create([
        'name' => 'Nội Thất Bắc Âu',
        'slug' => 'noi-that-bac-au',
        'is_active' => true,
    ]);

    $this->productSingleVnd = Product::create([
        'name' => 'Đinh Ốc Nhỏ',
        'slug' => 'dinh-oc-nho',
        'sku' => 'SCREW-001',
        'price' => 1, // 1 VND for exact boundary testing
        'stock' => 1000000,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => false,
    ]);

    $this->productA = Product::create([
        'name' => 'Ghế Gỗ Sồi Armchair',
        'slug' => 'ghe-go-soi-armchair',
        'sku' => 'CHR-ARM-01',
        'price' => 250000,
        'stock' => 50,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => false,
    ]);

    $this->productB = Product::create([
        'name' => 'Bàn Cafe Tròn Scandinavian',
        'slug' => 'ban-cafe-tron-scandinavian',
        'sku' => 'TBL-COF-01',
        'price' => 500000,
        'stock' => 20,
        'category_id' => $this->category->id,
        'status' => 'published',
        'is_featured' => true,
    ]);
});

// Helper to create valid orders
function createTestOrder(Customer $customer, int $amount, OrderStatus $status = OrderStatus::Confirmed): Order
{
    return Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-'.uniqid(),
        'customer_name' => $customer->name,
        'phone' => '0901234567',
        'email' => $customer->email,
        'address' => '123 Đường Pasteur',
        'city' => 'Hồ Chí Minh',
        'district' => 'Quận 1',
        'ward' => 'Phường Bến Nghé',
        'status' => $status,
        'subtotal' => $amount,
        'total_amount' => $amount,
        'shipping_fee' => 0,
        'discount_amount' => 0,
    ]);
}

// =========================================================================
// ADVERSARIAL SECTION 1: Nudge Progress Bar Mathematical Boundaries
// =========================================================================

test('Adversarial 1.1: Free Shipping Nudge with 0 items in cart returns null without division by zero', function () {
    PromotionRule::create([
        'name' => 'Freeship 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    Session::put('cart', []);

    $component = Livewire::test(CartDrawer::class);

    expect($component->get('smartNudge'))->toBeNull();
    expect($component->get('subtotal'))->toBe(0.0);
    expect($component->get('totalQuantity'))->toBe(0);
});

test('Adversarial 1.2: Free Shipping Nudge at exactly 1 VND below threshold (499,999đ / 500,000đ)', function () {
    PromotionRule::create([
        'name' => 'Freeship 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // Construct cart totaling 499,999 VND
    Session::put('cart', [
        "{$this->productSingleVnd->id}_0" => [
            'product_id' => $this->productSingleVnd->id,
            'product_variant_id' => null,
            'quantity' => 499999, // 499,999 * 1 = 499,999đ
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->not->toBeNull();
    expect($nudge['type'])->toBe('free_shipping');
    expect($nudge['gap_amount'])->toBe(1.0);
    expect($nudge['target_amount'])->toBe(500000.0);
    expect($nudge['is_completed'])->toBeFalse();
    expect($nudge['badge'])->toBe('CÒN THIẾU 1₫');
    expect($nudge['message'])->toContain('Mua thêm 1₫ để nhận FREESHIP toàn quốc!');
    expect($nudge['progress_percent'])->toBeLessThanOrEqual(100.0);
});

test('Adversarial 1.3: Free Shipping Nudge at exactly threshold (500,000đ / 500,000đ)', function () {
    PromotionRule::create([
        'name' => 'Freeship 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // Exactly 500,000 VND
    Session::put('cart', [
        "{$this->productB->id}_0" => [
            'product_id' => $this->productB->id,
            'product_variant_id' => null,
            'quantity' => 1, // 1 * 500,000 = 500,000đ
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->not->toBeNull();
    expect($nudge['type'])->toBe('free_shipping');
    expect($nudge['gap_amount'])->toBe(0.0);
    expect($nudge['progress_percent'])->toBe(100.0);
    expect($nudge['is_completed'])->toBeTrue();
    expect($nudge['badge'])->toBe('FREESHIP ĐẠT 100%');
    expect($nudge['message'])->toContain('Tuyệt vời! Đơn hàng của bạn đã đủ điều kiện Freeship toàn quốc!');
});

test('Adversarial 1.4: Free Shipping Nudge at exactly 1 VND above threshold (500,001đ / 500,000đ)', function () {
    PromotionRule::create([
        'name' => 'Freeship 500K',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'discount_value' => 0.0,
        'min_order_amount' => 500000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // 500,000 + 1 = 500,001 VND
    Session::put('cart', [
        "{$this->productB->id}_0" => [
            'product_id' => $this->productB->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
        "{$this->productSingleVnd->id}_0" => [
            'product_id' => $this->productSingleVnd->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $nudge = $component->get('smartNudge');

    expect($nudge)->not->toBeNull();
    expect($nudge['type'])->toBe('free_shipping');
    expect($nudge['gap_amount'])->toBe(0.0);
    expect($nudge['progress_percent'])->toBe(100.0);
    expect($nudge['is_completed'])->toBeTrue();
    expect($nudge['badge'])->toBe('FREESHIP ĐẠT 100%');
});

// =========================================================================
// ADVERSARIAL SECTION 2: Tiered Quantity Step Transitions (1->2, 3->4, 5->6)
// =========================================================================

test('Adversarial 2.1: Tiered Quantity Step Transitions across full matrix (1 to 7 items)', function () {
    // Rule with 3 tiers: 2 items -> 5%, 4 items -> 10%, 6 items -> 15%
    PromotionRule::create([
        'name' => 'Chiết Khấu Bậc Thang',
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

    // Test matrix of item quantities
    $testScenarios = [
        1 => ['gap' => 1, 'progress' => 50.0, 'completed' => false, 'badge' => 'THÊM 1 SP → GIẢM 5%'],
        2 => ['gap' => 2, 'progress' => 50.0, 'completed' => false, 'badge' => 'THÊM 2 SP → GIẢM 10%'],
        3 => ['gap' => 1, 'progress' => 75.0, 'completed' => false, 'badge' => 'THÊM 1 SP → GIẢM 10%'],
        4 => ['gap' => 2, 'progress' => 66.7, 'completed' => false, 'badge' => 'THÊM 2 SP → GIẢM 15%'],
        5 => ['gap' => 1, 'progress' => 83.3, 'completed' => false, 'badge' => 'THÊM 1 SP → GIẢM 15%'],
        6 => ['gap' => 0, 'progress' => 100.0, 'completed' => true, 'badge' => 'ĐẠT MỨC GIẢM 15%'],
        7 => ['gap' => 0, 'progress' => 100.0, 'completed' => true, 'badge' => 'ĐẠT MỨC GIẢM 15%'],
    ];

    foreach ($testScenarios as $qty => $expected) {
        Session::put('cart', [
            "{$this->productA->id}_0" => [
                'product_id' => $this->productA->id,
                'product_variant_id' => null,
                'quantity' => $qty,
            ],
        ]);

        $component = Livewire::test(CartDrawer::class);
        $nudge = $component->get('smartNudge');

        expect($nudge)->not->toBeNull("Failed at qty: {$qty}");
        expect($nudge['type'])->toBe('tiered_quantity');
        expect($nudge['gap_quantity'])->toBe($expected['gap'], "Gap mismatch at qty: {$qty}");
        expect($nudge['progress_percent'])->toBe($expected['progress'], "Progress mismatch at qty: {$qty}");
        expect($nudge['is_completed'])->toBe($expected['completed'], "Completed status mismatch at qty: {$qty}");
        expect($nudge['badge'])->toBe($expected['badge'], "Badge mismatch at qty: {$qty}");
    }
});

// =========================================================================
// ADVERSARIAL SECTION 3: 1-Click Coupon Tray Customer Tier Restrictions
// =========================================================================

test('Adversarial 3.1: Guest customer eligibility across various tier rules', function () {
    // Rule for ALL
    PromotionRule::create([
        'name' => 'Mã Mọi Khách Hàng',
        'code' => 'ALL10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'target_customer_tier' => PromotionRule::TIER_ALL,
        'priority' => 1,
        'is_active' => true,
    ]);

    // Rule for GOLD
    PromotionRule::create([
        'name' => 'Mã VIP Gold',
        'code' => 'GOLD20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'target_customer_tier' => PromotionRule::TIER_GOLD,
        'priority' => 2,
        'is_active' => true,
    ]);

    // Rule for FIRST_TIME
    PromotionRule::create([
        'name' => 'Mã Khách Mới',
        'code' => 'FIRST15',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 15.0,
        'target_customer_tier' => PromotionRule::TIER_FIRST_TIME,
        'priority' => 3,
        'is_active' => true,
    ]);

    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    // No authenticated customer (Guest)
    $component = Livewire::test(CartDrawer::class);
    $coupons = $component->get('availableCoupons');

    $allCoupon = $coupons->firstWhere('code', 'ALL10');
    expect($allCoupon->is_eligible)->toBeTrue();
    expect($allCoupon->ineligible_reason)->toBeNull();

    $goldCoupon = $coupons->firstWhere('code', 'GOLD20');
    expect($goldCoupon->is_eligible)->toBeFalse();
    expect($goldCoupon->ineligible_reason)->toBe('Cần đăng nhập');

    $firstCoupon = $coupons->firstWhere('code', 'FIRST15');
    expect($firstCoupon->is_eligible)->toBeTrue(); // Guest with no prior email orders is eligible
});

test('Adversarial 3.2: Bronze vs Gold authenticated customer tier segregation', function () {
    PromotionRule::create([
        'name' => 'Mã VIP Gold 20%',
        'code' => 'GOLD20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'target_customer_tier' => PromotionRule::TIER_GOLD,
        'priority' => 1,
        'is_active' => true,
    ]);

    PromotionRule::create([
        'name' => 'Mã Hạng Bronze 5%',
        'code' => 'BRONZE5',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 5.0,
        'target_customer_tier' => PromotionRule::TIER_BRONZE,
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

    // 1. Test as Bronze Customer (1,000,000 VND spend < 20M)
    $bronzeCustomer = Customer::create([
        'name' => 'Khách Bronze',
        'email' => 'bronze@example.com',
        'password' => bcrypt('secret123'),
    ]);
    createTestOrder($bronzeCustomer, 1000000);

    $this->actingAs($bronzeCustomer, 'customer');

    $component = Livewire::test(CartDrawer::class);
    $coupons = $component->get('availableCoupons');

    $bronzeCoupon = $coupons->firstWhere('code', 'BRONZE5');
    expect($bronzeCoupon->is_eligible)->toBeTrue();

    $goldCoupon = $coupons->firstWhere('code', 'GOLD20');
    expect($goldCoupon->is_eligible)->toBeFalse();
    expect($goldCoupon->ineligible_reason)->toBe('Chưa đủ điều kiện');

    // 2. Test as Gold Customer (25,000,000 VND spend >= 20M)
    $goldCustomer = Customer::create([
        'name' => 'Khách VIP Gold',
        'email' => 'gold@example.com',
        'password' => bcrypt('secret123'),
    ]);
    createTestOrder($goldCustomer, 25000000);

    $this->actingAs($goldCustomer, 'customer');

    $componentGold = Livewire::test(CartDrawer::class);
    $couponsGold = $componentGold->get('availableCoupons');

    expect($couponsGold->firstWhere('code', 'GOLD20')->is_eligible)->toBeTrue();
    expect($couponsGold->firstWhere('code', 'BRONZE5')->is_eligible)->toBeTrue();
});

test('Adversarial 3.3: First-time customer tier with and without prior orders', function () {
    PromotionRule::create([
        'name' => 'Mã Đơn Đầu Tiên',
        'code' => 'FIRST10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'target_customer_tier' => PromotionRule::TIER_FIRST_TIME,
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

    // Customer with 0 orders
    $newCustomer = Customer::create([
        'name' => 'Khách Mới Chưa Mua',
        'email' => 'new@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($newCustomer, 'customer');

    $componentNew = Livewire::test(CartDrawer::class);
    expect($componentNew->get('availableCoupons')->firstWhere('code', 'FIRST10')->is_eligible)->toBeTrue();

    // Customer with existing confirmed order
    $returningCustomer = Customer::create([
        'name' => 'Khách Đã Từng Mua',
        'email' => 'returning@example.com',
        'password' => bcrypt('secret123'),
    ]);
    createTestOrder($returningCustomer, 500000);

    $this->actingAs($returningCustomer, 'customer');

    $componentReturning = Livewire::test(CartDrawer::class);
    $coupon = $componentReturning->get('availableCoupons')->firstWhere('code', 'FIRST10');
    expect($coupon->is_eligible)->toBeFalse();
    expect($coupon->ineligible_reason)->toBe('Chưa đủ điều kiện');
});

// =========================================================================
// ADVERSARIAL SECTION 4: Min Order & Min Quantity Near-Miss Messaging
// =========================================================================

test('Adversarial 4.1: Min Order Threshold near-miss calculation in availableCoupons tray and applyCoupon error', function () {
    PromotionRule::create([
        'name' => 'Giảm 100K Đơn 1 Triệu',
        'code' => 'MIN1M',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 100000.0,
        'min_order_amount' => 1000000.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    // Cart subtotal: 250,000 VND (Gap: 750,000 VND)
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $coupon = $component->get('availableCoupons')->firstWhere('code', 'MIN1M');

    expect($coupon->is_eligible)->toBeFalse();
    expect($coupon->ineligible_reason)->toBe('Mua thêm 750.000₫');

    // Attempting applyCoupon triggers specific error toast and message
    $component->call('applyCoupon', 'MIN1M')
        ->assertSet('couponError', 'Mã [MIN1M] yêu cầu đơn tối thiểu 1.000.000₫ (Cần thêm 750.000₫).')
        ->assertDispatched('toast', message: 'Mã [MIN1M] yêu cầu đơn tối thiểu 1.000.000₫ (Cần thêm 750.000₫).', type: 'error');
});

test('Adversarial 4.2: Min Quantity Threshold near-miss calculation in availableCoupons tray and applyCoupon error', function () {
    PromotionRule::create([
        'name' => 'Giảm 50K Khi Mua Từ 5 SP',
        'code' => 'QTY5',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 50000.0,
        'min_quantity' => 5,
        'priority' => 1,
        'is_active' => true,
    ]);

    // Cart has 2 items (Gap: 3 items)
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);
    $coupon = $component->get('availableCoupons')->firstWhere('code', 'QTY5');

    expect($coupon->is_eligible)->toBeFalse();
    expect($coupon->ineligible_reason)->toBe('Thêm 3 sản phẩm');

    // Attempting applyCoupon triggers quantity-specific error
    $component->call('applyCoupon', 'QTY5')
        ->assertSet('couponError', 'Mã [QTY5] yêu cầu tối thiểu 5 sản phẩm (Cần thêm 3 sản phẩm).')
        ->assertDispatched('toast', message: 'Mã [QTY5] yêu cầu tối thiểu 5 sản phẩm (Cần thêm 3 sản phẩm).', type: 'error');
});

// =========================================================================
// ADVERSARIAL SECTION 5: 1-Click Apply and Remove Toggling & State Cycle
// =========================================================================

test('Adversarial 5.1: 1-Click Apply and Remove toggling state cycle test', function () {
    PromotionRule::create([
        'name' => 'Giảm 10% Coupon A',
        'code' => 'COUPON10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10.0,
        'priority' => 1,
        'is_active' => true,
    ]);

    PromotionRule::create([
        'name' => 'Giảm 20% Coupon B',
        'code' => 'COUPON20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20.0,
        'priority' => 2,
        'is_active' => true,
    ]);

    // 2x Product A = 500,000 VND
    Session::put('cart', [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2,
        ],
    ]);

    $component = Livewire::test(CartDrawer::class);

    // Initial state: No coupon
    expect($component->get('appliedCouponCode'))->toBeNull();
    expect($component->get('totalDiscount'))->toBe(0.0);
    expect($component->get('netTotal'))->toBe(530000.0); // 500k subtotal + 30k shipping

    // 1. Apply COUPON10
    $component->call('applyCoupon', 'COUPON10')
        ->assertSet('appliedCouponCode', 'COUPON10')
        ->assertSet('totalDiscount', 50000.0) // 10% of 500k
        ->assertSet('netTotal', 480000.0)     // 500k + 30k - 50k
        ->assertDispatched('coupon-applied');
    expect(Session::get('coupon'))->toBe('COUPON10');

    // 2. Remove coupon
    $component->call('removeCoupon')
        ->assertSet('appliedCouponCode', null)
        ->assertSet('totalDiscount', 0.0)
        ->assertSet('netTotal', 530000.0)
        ->assertDispatched('coupon-removed');
    expect(Session::has('coupon'))->toBeFalse();

    // 3. Apply COUPON20 directly
    $component->call('applyCoupon', 'COUPON20')
        ->assertSet('appliedCouponCode', 'COUPON20')
        ->assertSet('totalDiscount', 100000.0) // 20% of 500k
        ->assertSet('netTotal', 430000.0)      // 500k + 30k - 100k
        ->assertDispatched('coupon-applied');
    expect(Session::get('coupon'))->toBe('COUPON20');

    // 4. Switch from COUPON20 to COUPON10 without explicit remove
    $component->call('applyCoupon', 'COUPON10')
        ->assertSet('appliedCouponCode', 'COUPON10')
        ->assertSet('totalDiscount', 50000.0)
        ->assertSet('netTotal', 480000.0);
    expect(Session::get('coupon'))->toBe('COUPON10');

    // 5. Final remove
    $component->call('removeCoupon')
        ->assertSet('appliedCouponCode', null)
        ->assertSet('totalDiscount', 0.0);
    expect(Session::has('coupon'))->toBeFalse();
});
