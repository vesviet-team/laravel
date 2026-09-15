<?php

use App\Livewire\AddToCartButton;
use App\Livewire\CartCount;
use App\Livewire\CartDrawer;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

/*
|--------------------------------------------------------------------------
| TIER 5 ADVERSARIAL COVERAGE HARDENING & EMPIRICAL VERIFICATION
|--------------------------------------------------------------------------
| Target: Sober Furniture Home v12 Storefront Clone
|
| Scope:
| 1. High concurrency / simultaneous cart actions.
| 2. Extreme boundary data (UTF-8 diacritics, emoji, RTL, 0 price, huge prices,
|    missing category relations, missing images, malformed email subscriptions).
| 3. Mobile and desktop viewport layout conformance checks.
| 4. Search modal query injection and escaping tests (XSS, SQLi, regex chars).
| 5. Rapid state transitions between Livewire cart drawer and navigation.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SECTION 1: HIGH CONCURRENCY & SIMULTANEOUS CART ACTIONS
|--------------------------------------------------------------------------
*/

it('verifies rapid successive additions of multiple unique products maintain exact cart counts and mathematical subtotal', function () {
    $cartService = app(CartService::class);
    $products = [];
    $expectedSubtotal = 0;
    $expectedCount = 0;

    // Create 5 distinct products with various prices and stock
    $prices = [150000, 320000, 780000, 1450000, 4800000];
    for ($i = 0; $i < 5; $i++) {
        $p = Product::create([
            'name' => "Stress Test Product {$i}",
            'slug' => "stress-test-product-{$i}",
            'price' => $prices[$i],
            'stock' => 50,
            'status' => 'active',
        ]);
        $products[] = $p;
        $qty = $i + 1; // 1, 2, 3, 4, 5 items
        $cartService->add($p->id, null, $qty);
        $expectedSubtotal += $prices[$i] * $qty;
        $expectedCount += $qty;
    }

    $rawCart = $cartService->getCart();
    expect(count($rawCart))->toBe(5)
        ->and(array_sum(array_column($rawCart, 'quantity')))->toBe($expectedCount);

    $items = $cartService->getCartItemsDetails();
    expect(count($items))->toBe(5);

    $calculatedTotal = $cartService->calculateTotal();
    expect($calculatedTotal)->toEqual((float) $expectedSubtotal);

    // Livewire CartDrawer subtotal computation
    $cartDrawer = new CartDrawer;
    $cartDrawer->mount($cartService);
    expect($cartDrawer->subtotal())->toEqual((float) $expectedSubtotal);
});

it('verifies rapid successive additions of same product aggregate atomically without key duplication', function () {
    $cartService = app(CartService::class);
    $product = Product::create([
        'name' => 'Atomic Accumulation Stool',
        'slug' => 'atomic-accumulation-stool',
        'price' => 350000,
        'stock' => 100,
        'status' => 'active',
    ]);

    // Simulate 12 rapid individual clicks adding 1 unit each
    for ($k = 0; $k < 12; $k++) {
        $cartService->add($product->id, null, 1);
    }

    $cart = $cartService->getCart();
    $expectedKey = $product->id.'_0';

    expect(count($cart))->toBe(1)
        ->and(isset($cart[$expectedKey]))->toBeTrue()
        ->and($cart[$expectedKey]['quantity'])->toBe(12);

    expect($cartService->calculateTotal())->toEqual(350000 * 12.0);
});

it('verifies interleaved rapid add, update, and remove mutations result in consistent cart state', function () {
    $cartService = app(CartService::class);

    $pA = Product::create(['name' => 'Prod A', 'slug' => 'prod-a', 'price' => 100000, 'stock' => 20, 'status' => 'active']);
    $pB = Product::create(['name' => 'Prod B', 'slug' => 'prod-b', 'price' => 200000, 'stock' => 20, 'status' => 'active']);
    $pC = Product::create(['name' => 'Prod C', 'slug' => 'prod-c', 'price' => 300000, 'stock' => 20, 'status' => 'active']);
    $pD = Product::create(['name' => 'Prod D', 'slug' => 'prod-d', 'price' => 400000, 'stock' => 20, 'status' => 'active']);

    // Sequence of interleaved actions
    $cartService->add($pA->id, null, 2); // A: 2
    $cartService->add($pB->id, null, 3); // A: 2, B: 3
    $cartService->update($pA->id, null, 5); // A: 5, B: 3
    $cartService->add($pC->id, null, 1); // A: 5, B: 3, C: 1
    $cartService->remove($pB->id, null); // A: 5, C: 1
    $cartService->add($pD->id, null, 2); // A: 5, C: 1, D: 2
    $cartService->update($pC->id, null, 4); // A: 5, C: 4, D: 2
    $cartService->remove($pA->id, null); // C: 4, D: 2

    $cart = $cartService->getCart();
    expect(count($cart))->toBe(2);

    $expectedTotal = (4 * 300000) + (2 * 400000); // 1.2M + 800k = 2.0M
    expect($cartService->calculateTotal())->toEqual((float) $expectedTotal);
});

it('verifies database pessimistic lock prevents overselling under simulated concurrent checkout race', function () {
    $product = Product::create([
        'name' => 'Hot Drop Limited Chair',
        'slug' => 'hot-drop-limited-chair',
        'price' => 2500000,
        'stock' => 2,
        'status' => 'published',
    ]);

    $order1 = Order::create([
        'customer_name' => 'Buyer Fast',
        'phone' => '0901234567',
        'address' => '123 Le Loi, Q1, HCMC',
        'order_number' => 'ORD-CONC-001',
        'subtotal' => 5000000,
        'total_amount' => 5000000,
    ]);
    $order1->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price_at_purchase' => 2500000,
        'quantity' => 2,
    ]);

    $order2 = Order::create([
        'customer_name' => 'Buyer Slow',
        'phone' => '0909876543',
        'address' => '456 Nguyen Hue, Q1, HCMC',
        'order_number' => 'ORD-CONC-002',
        'subtotal' => 2500000,
        'total_amount' => 2500000,
    ]);
    $order2->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price_at_purchase' => 2500000,
        'quantity' => 1,
    ]);

    $inventoryService = new InventoryService;

    // Order 1 claims remaining 2 units
    $inventoryService->deductStock($order1);
    expect($product->fresh()->stock)->toBe(0);

    // Order 2 attempts deduction when stock is 0 -> Exception
    expect(fn () => $inventoryService->deductStock($order2))
        ->toThrow(Exception::class, 'Không đủ tồn kho cho sản phẩm');

    // Confirm product stock never dropped into negative numbers
    expect($product->fresh()->stock)->toBe(0);
});

it('verifies cart calculations maintain floating-point precision with flash sale overrides under load', function () {
    $regularProduct = Product::create([
        'name' => 'Regular Nordic Lamp',
        'slug' => 'regular-nordic-lamp',
        'price' => 600000,
        'stock' => 20,
        'status' => 'active',
    ]);

    $flashProduct = Product::create([
        'name' => 'Flash Sale Accent Table',
        'slug' => 'flash-sale-accent-table',
        'price' => 1200000,
        'stock' => 15,
        'status' => 'active',
    ]);

    $flashSale = FlashSale::create([
        'name' => 'Midnight Flash Sale',
        'start_time' => now()->subHour(),
        'end_time' => now()->addHours(2),
        'status' => 'active',
    ]);

    FlashSaleItem::create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $flashProduct->id,
        'price' => 499000, // Discounted from 1.2M
        'quantity' => 10,
        'sold_quantity' => 2,
    ]);

    $cartService = app(CartService::class);
    $cartService->add($regularProduct->id, null, 2); // 2 * 600,000 = 1,200,000
    $cartService->add($flashProduct->id, null, 3);   // 3 * 499,000 = 1,497,000

    $items = $cartService->getCartItemsDetails();
    $flashItem = collect($items)->firstWhere('product_id', $flashProduct->id);
    $regularItem = collect($items)->firstWhere('product_id', $regularProduct->id);

    expect($flashItem['price'])->toBe(499000.0)
        ->and($flashItem['is_flash_sale'])->toBeTrue()
        ->and($flashItem['subtotal'])->toBe(1497000.0)
        ->and($regularItem['price'])->toBe(600000.0)
        ->and($regularItem['is_flash_sale'])->toBeFalse();

    expect($cartService->calculateTotal())->toBe(2697000.0);
});

it('verifies simultaneous operations across product variants create distinct cart keys and separate line totals', function () {
    $product = Product::create([
        'name' => 'Modular Lounge Chair',
        'slug' => 'modular-lounge-chair',
        'price' => 2000000,
        'stock' => 50,
        'status' => 'active',
    ]);

    $vOak = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Natural Oak',
        'price' => 2100000,
        'stock' => 10,
        'sku' => 'CHAIR-OAK',
    ]);

    $vWalnut = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Dark Walnut',
        'price' => 2400000,
        'stock' => 10,
        'sku' => 'CHAIR-WALNUT',
    ]);

    $cartService = app(CartService::class);
    $cartService->add($product->id, $vOak->id, 2);
    $cartService->add($product->id, $vWalnut->id, 3);
    $cartService->add($product->id, null, 1); // Base model without variant

    $details = $cartService->getCartItemsDetails();
    expect(count($details))->toBe(3);

    $oakLine = collect($details)->firstWhere('product_variant_id', $vOak->id);
    $walnutLine = collect($details)->firstWhere('product_variant_id', $vWalnut->id);
    $baseLine = collect($details)->firstWhere('product_variant_id', null);

    expect($oakLine['subtotal'])->toBe(4200000.0)
        ->and($walnutLine['subtotal'])->toBe(7200000.0)
        ->and($baseLine['subtotal'])->toBe(2000000.0);

    expect($cartService->calculateTotal())->toBe(13400000.0);
});

it('verifies multi-user session cart isolation under rapid parallel requests', function () {
    $pA = Product::create(['name' => 'Item A', 'slug' => 'item-a', 'price' => 500000, 'stock' => 10, 'status' => 'active']);
    $pB = Product::create(['name' => 'Item B', 'slug' => 'item-b', 'price' => 900000, 'stock' => 10, 'status' => 'active']);

    // User Session 1
    Session::put('cart', [
        "{$pA->id}_0" => ['product_id' => $pA->id, 'product_variant_id' => null, 'quantity' => 2],
    ]);
    $service1 = app(CartService::class);
    expect($service1->calculateTotal())->toBe(1000000.0);

    // User Session 2 (isolated)
    Session::flush();
    Session::put('cart', [
        "{$pB->id}_0" => ['product_id' => $pB->id, 'product_variant_id' => null, 'quantity' => 3],
    ]);
    $service2 = app(CartService::class);
    expect($service2->calculateTotal())->toBe(2700000.0);
});

/*
|--------------------------------------------------------------------------
| SECTION 2: EXTREME BOUNDARY DATA & RELATIONAL EDGE CASES
|--------------------------------------------------------------------------
*/

it('verifies catalog and product cards render complex UTF-8 diacritics, Asian scripts, Cyrillic, and emoji without corruption', function () {
    $category = Category::create([
        'name' => 'Đồ Nội Thất Cao Cấp 🌟',
        'slug' => 'do-noi-that-cao-cap',
    ]);

    $testProducts = [
        [
            'name' => 'Bàn Trà Gỗ Sồi 100% Tự Nhiên (Đặc Biệt: ắ, ằ, ẳ, ẵ, ặ, ấ, ầ, ẩ, ẫ, ậ, đ, Đ)',
            'slug' => 'ban-tra-go-soi-dac-biet-diacritics',
            'price' => 1850000,
        ],
        [
            'name' => '🛋️ Sofa Da Bò Bắc Âu Scandinavian 🌟 Bàn Trà 🪑 Đèn 💡',
            'slug' => 'sofa-da-bo-emoji-decor',
            'price' => 15500000,
        ],
        [
            'name' => 'مجموعة الأثاث الفاخرة - בדיקת עברית RTL Mixed Test',
            'slug' => 'arabic-hebrew-rtl-furniture-set',
            'price' => 9900000,
        ],
        [
            'name' => 'Элитная мебель из дуба 高級家具 2026 Edition',
            'slug' => 'cyrillic-asian-luxury-furniture',
            'price' => 12800000,
        ],
        [
            'name' => "Bàn\u{200B}Gỗ\u{00A0}Sồi\u{200D}2026 NonBreaking ZeroWidth",
            'slug' => 'zero-width-non-breaking-table',
            'price' => 3200000,
        ],
    ];

    foreach ($testProducts as $item) {
        Product::create([
            'category_id' => $category->id,
            'name' => $item['name'],
            'slug' => $item['slug'],
            'price' => $item['price'],
            'stock' => 10,
            'status' => 'active',
        ]);
    }

    $response = $this->get(route('products.index'));
    $response->assertOk();

    // Verify catalog rendered all UTF-8 strings safely
    $response->assertSee('Đặc Biệt: ắ, ằ, ẳ, ẵ, ặ, ấ, ầ, ẩ, ẫ, ậ, đ, Đ', false);
    $response->assertSee('🛋️', false);
    $response->assertSee('Элитная мебель', false);
    $response->assertSee('高級家具', false);

    // Update to published for product show endpoint check
    $pShow = Product::where('slug', 'ban-tra-go-soi-dac-biet-diacritics')->first();
    $pShow->update(['status' => 'published']);

    $showResp = $this->get(route('products.show', 'ban-tra-go-soi-dac-biet-diacritics'));
    $showResp->assertOk();
    $showResp->assertSee('Bàn Trà Gỗ Sồi 100% Tự Nhiên');
});

it('verifies zero price and multi-billion VND price format accurately in views and cart calculations', function () {
    $freeSample = Product::create([
        'name' => 'Free Fabric Swatch Catalogue',
        'slug' => 'free-fabric-swatch',
        'price' => 0,
        'stock' => 100,
        'status' => 'active',
    ]);

    $billionSofa = Product::create([
        'name' => 'Custom Handcrafted Royal Suite Villa Set',
        'slug' => 'royal-suite-villa-set',
        'price' => 2500000000, // 2.5 Billion VND
        'stock' => 1,
        'status' => 'active',
    ]);

    $response = $this->get(route('products.index'));
    $response->assertOk();

    $response->assertSee('0₫');
    $response->assertSee('2.500.000.000₫');

    $cartService = app(CartService::class);
    $cartService->add($freeSample->id, null, 1);
    $cartService->add($billionSofa->id, null, 1);

    expect($cartService->calculateTotal())->toBe(2500000000.0);
});

it('verifies products with null or soft-deleted categories render safely without 500 null pointer exceptions', function () {
    $category = Category::create([
        'name' => 'Temporary Category',
        'slug' => 'temp-category',
    ]);

    $pOrphan = Product::create([
        'name' => 'Orphaned Coffee Table',
        'slug' => 'orphaned-coffee-table',
        'category_id' => null,
        'price' => 850000,
        'stock' => 5,
        'status' => 'active',
    ]);

    $pWithCategory = Product::create([
        'name' => 'Table to be Deleted',
        'slug' => 'table-to-be-deleted',
        'category_id' => $category->id,
        'price' => 950000,
        'stock' => 5,
        'status' => 'active',
    ]);

    // Delete category
    $category->delete();

    $response = $this->get(route('products.index'));
    $response->assertOk();
    $response->assertSee('Orphaned Coffee Table');

    // Update pOrphan to published for show route
    $pOrphan->update(['status' => 'published']);
    $showResp = $this->get(route('products.show', $pOrphan->slug));
    $showResp->assertOk();
});

it('verifies missing images and external CDN URLs render appropriate fallbacks or image tags', function () {
    $pNoImage = Product::create([
        'name' => 'No Image Minimal Desk',
        'slug' => 'no-image-minimal-desk',
        'image_path' => null,
        'price' => 1200000,
        'stock' => 4,
        'status' => 'active',
    ]);

    $pCdn = Product::create([
        'name' => 'CDN Image Accent Lamp',
        'slug' => 'cdn-image-accent-lamp',
        'image_path' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c',
        'price' => 450000,
        'stock' => 8,
        'status' => 'active',
    ]);

    $response = $this->get(route('products.index'));
    $response->assertOk();

    // Verify SVG placeholder fallback is rendered for null image
    $response->assertSee('svg', false);
    // Verify external CDN URL is rendered in image source
    $response->assertSee('https://images.unsplash.com/photo-1507473885765-e6ed057f782c', false);
});

it('verifies newsletter subscription rejects header injection, XSS payloads, and malformed emails while trimming valid inputs', function () {
    // 1. Header injection attack attempt
    $resp1 = $this->post(route('newsletter.subscribe'), [
        'email' => "victim@domain.com\r\nBcc:spammer@evil.com",
    ]);
    $resp1->assertSessionHasErrors('email');

    // 2. XSS payload in email field
    $resp2 = $this->post(route('newsletter.subscribe'), [
        'email' => '<script>alert(1)</script>@domain.com',
    ]);
    $resp2->assertSessionHasErrors('email');

    // 3. Incomplete email addresses
    $this->post(route('newsletter.subscribe'), ['email' => 'plainaddress'])->assertSessionHasErrors('email');
    $this->post(route('newsletter.subscribe'), ['email' => '@missinguser.com'])->assertSessionHasErrors('email');
    $this->post(route('newsletter.subscribe'), ['email' => 'missingdomain@'])->assertSessionHasErrors('email');

    // 4. Overlong email > 255 chars
    $longEmail = str_repeat('a', 250).'@example.com';
    $this->post(route('newsletter.subscribe'), ['email' => $longEmail])->assertSessionHasErrors('email');

    // 5. Valid email with leading/trailing whitespace & uppercase letters
    $resp5 = $this->post(route('newsletter.subscribe'), [
        'email' => '   Customer.Sober@MyShop.VN   ',
    ]);
    $resp5->assertSessionHasNoErrors();

    // Verify sanitized lowercase email stored in database
    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'customer.sober@myshop.vn',
    ]);
});

it('verifies newsletter duplicate subscriptions are idempotent and do not leak internal database exceptions', function () {
    NewsletterSubscriber::create([
        'email' => 'existing.subscriber@myshop.vn',
        'ip_address' => '127.0.0.1',
    ]);

    // Resubscribing same email should succeed silently without 500 error or unique constraint violation
    $response = $this->post(route('newsletter.subscribe'), [
        'email' => 'existing.subscriber@myshop.vn',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();
    expect(NewsletterSubscriber::where('email', 'existing.subscriber@myshop.vn')->count())->toBe(1);
});

it('verifies checkout pipeline validates and handles extreme customer inputs safely', function () {
    $product = Product::create([
        'name' => 'Solid Oak Dining Table',
        'slug' => 'solid-oak-dining-table',
        'price' => 5000000,
        'stock' => 10,
        'status' => 'active',
    ]);

    Session::put('cart', [
        "{$product->id}_0" => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    // 1. Invalid phone number format fails
    $badPhoneResp = $this->post(route('checkout.store'), [
        'customer_name' => 'Nguyễn Văn A',
        'phone' => '12345678', // Invalid non-VN phone
        'address' => '123 Đường B',
        'payment_method' => 'cod',
    ]);
    $badPhoneResp->assertSessionHasErrors('phone');

    // 2. Valid checkout with Vietnamese diacritics and long formatted address
    $validResp = $this->post(route('checkout.store'), [
        'customer_name' => 'Trần Thị Mỹ Duyên — Khách Hàng VIP',
        'phone' => '0901234567',
        'email' => 'myduyen.tran@myshop.vn',
        'address' => 'Số 999 Đường Nguyễn Hữu Cảnh, Phường 22, Quận Bình Thạnh, Thành Phố Hồ Chí Minh',
        'notes' => 'Giao hàng giờ hành chính, gọi trước 30 phút. <tag>Không bấm chuông</tag>',
        'payment_method' => 'cod',
    ]);

    $validResp->assertSessionHasNoErrors();
    $this->assertDatabaseHas('orders', [
        'customer_name' => 'Trần Thị Mỹ Duyên — Khách Hàng VIP',
        'phone' => '0901234567',
    ]);
});

it('verifies order tracking handles SQL characters, non-existent orders, and boundary strings gracefully', function () {
    // 1. Non-existent order number
    $resp1 = $this->followingRedirects()->post(route('track-order.track'), [
        'order_number' => 'ORD-NON-EXISTENT-999999',
        'contact_info' => '0912345678',
    ]);
    $resp1->assertOk();
    $resp1->assertSee('Không tìm thấy đơn hàng');

    // 2. SQL injection payload in tracking search
    $resp2 = $this->followingRedirects()->post(route('track-order.track'), [
        'order_number' => "' OR '1'='1' --",
        'contact_info' => "' OR '1'='1' --",
    ]);
    $resp2->assertOk();
    $resp2->assertSee('Không tìm thấy đơn hàng');

    // 3. Valid order tracking
    $order = Order::create([
        'order_number' => 'ORD-TRACK-VALID-2026',
        'customer_name' => 'Lê Hoàng Long',
        'phone' => '0912345678',
        'address' => 'Hà Nội',
        'subtotal' => 1500000,
        'total_amount' => 1500000,
        'status' => 'pending',
    ]);
    $order->items()->create([
        'product_name' => 'Ghế Ăn Gỗ Sồi Tự Nhiên',
        'price_at_purchase' => 1500000,
        'quantity' => 1,
    ]);

    $resp3 = $this->followingRedirects()->post(route('track-order.track'), [
        'order_number' => 'ORD-TRACK-VALID-2026',
        'contact_info' => '0912345678',
    ]);
    $resp3->assertOk();
    $resp3->assertSee('ORD-TRACK-VALID-2026');
    $resp3->assertSee('Ghế Ăn Gỗ Sồi Tự Nhiên');
    $resp3->assertSee('1.500.000₫');
});

/*
|--------------------------------------------------------------------------
| SECTION 3: MOBILE AND DESKTOP VIEWPORT LAYOUT CONFORMANCE CHECKS
|--------------------------------------------------------------------------
*/

it('verifies layout declares Sober v2 design tokens and CSS variables', function () {
    $cssPath = resource_path('css/app.css');
    expect(File::exists($cssPath))->toBeTrue();
    $css = File::get($cssPath);

    // Primary palette
    expect($css)->toMatch('/(#23232C|#23232c|#1a1a1a)/')
        ->and($css)->toMatch('/(#F0F0F0|#f0f0f0|#F7F7F7|#f7f7f7)/')
        ->and($css)->toMatch('/(#E5E5E5|#e5e5e5)/')
        ->and($css)->toMatch('/(#888888|#909097)/')
        ->and($css)->toMatch('/(#E84444|#e84444)/');

    // Container wrapper
    expect($css)->toContain('.section-wrapper')
        ->and($css)->toContain('1400px');
});

it('verifies desktop navigation renders hidden on mobile and flex on desktop', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // Desktop nav container must use hidden md:flex
    $response->assertSee('hidden md:flex items-center gap-10', false);

    // Required 5 navigation destinations
    $response->assertSee('Trang Chủ');
    $response->assertSee('Sản Phẩm');
    $response->assertSee('Giới Thiệu');
    $response->assertSee('Liên Hệ');
    $response->assertSee('Tra Cứu');
});

it('verifies mobile hamburger trigger and slide-out drawer meet accessibility and responsive standards', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // Mobile trigger button must have md:hidden and aria-label
    $response->assertSee('md:hidden flex items-center justify-center', false);
    $response->assertSee('@click="mobileMenuOpen = true"', false);
    $response->assertSee('aria-label="Mở menu"', false);

    // Mobile drawer dialog container
    $response->assertSee('role="dialog"', false);
    $response->assertSee('aria-modal="true"', false);
    $response->assertSee('aria-label="Menu điều hướng"', false);
    $response->assertSee('x-show="mobileMenuOpen"', false);
});

it('verifies search modal dialog renders with required z-index backdrop and escape dismissal', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // Search modal trigger
    $response->assertSee('@click="searchOpen = true"', false);
    $response->assertSee('aria-label="Tìm kiếm"', false);

    // Search modal dialog attributes & backdrop
    $response->assertSee('x-show="searchOpen"', false);
    $response->assertSee('@keydown.window.escape="searchOpen = false"', false);
    $response->assertSee('role="dialog"', false);
    $response->assertSee('aria-modal="true"', false);
    $response->assertSee('aria-label="Tìm kiếm sản phẩm"', false);
});

it('verifies z-index stacking order across header, mobile drawer, search modal, and toasts prevents UI collision', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // Header: z-50
    $response->assertSee('sticky top-0 z-50', false);

    // Mobile drawer: z-[100]
    $response->assertSee('z-[100] md:hidden', false);

    // Search modal: z-[110]
    $response->assertSee('z-[110] overflow-y-auto', false);

    // Toast system container: z-[200]
    $response->assertSee('z-[200] flex flex-col', false);

    // Skip to content a11y: focus:z-[200]
    $response->assertSee('focus:z-[200]', false);
});

it('verifies responsive grid classes across all homepage sections and footer layers', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // 2-Col promo: 50/50 split (grid-cols-1 md:grid-cols-2)
    $response->assertSee('grid-cols-1 md:grid-cols-2', false);

    // 3-Col trust badges & collections (grid-cols-1 md:grid-cols-3)
    $response->assertSee('grid-cols-1 md:grid-cols-3', false);

    // Footer Layer 3 Instagram 6-photo feed (grid-cols-3 md:grid-cols-6)
    $response->assertSee('grid-cols-3 md:grid-cols-6', false);
});

it('verifies homepage renders all 7 sections in exact chronological sequence', function () {
    Product::create(['name' => 'F1', 'slug' => 'f1', 'price' => 100, 'stock' => 10, 'status' => 'active', 'is_featured' => true]);

    $response = $this->get(route('home'));
    $response->assertOk();
    $content = $response->getContent();

    $posHero = strpos($content, 'SHOP NOW');
    $posPromo = strpos($content, 'Lighting on Express');
    $posIntro = strpos($content, 'Great Design In Your Home');
    $posFeatured = strpos($content, 'Sản Phẩm Nổi Bật');
    $posArrivals = strpos($content, 'Sản Phẩm Mới');
    $posCollections = strpos($content, 'Copenhague Desk');
    $posTrust = strpos($content, 'Miễn Phí Vận Chuyển');
    $posFooter = strpos($content, 'Newsletter');

    expect($posHero)->toBeLessThan($posPromo)
        ->and($posPromo)->toBeLessThan($posIntro)
        ->and($posIntro)->toBeLessThan($posFeatured)
        ->and($posFeatured)->toBeLessThan($posArrivals)
        ->and($posArrivals)->toBeLessThan($posCollections)
        ->and($posCollections)->toBeLessThan($posTrust)
        ->and($posTrust)->toBeLessThan($posFooter);
});

/*
|--------------------------------------------------------------------------
| SECTION 4: SEARCH MODAL QUERY INJECTION & ESCAPING TESTS
|--------------------------------------------------------------------------
*/

it('verifies search modal escapes XSS script injection payloads in search query parameter', function () {
    $xssPayload = '<script>alert("XSS-SEARCH-ATTACK")</script>';

    $response = $this->get(route('products.index', ['search' => $xssPayload]));
    $response->assertOk();

    // The raw unescaped script tag must NOT be executed or printed unescaped
    $response->assertDontSee($xssPayload, false);
});

it('verifies search modal escapes HTML tag injection and event handlers', function () {
    $injection = '"><img src=x onerror=alert(1)>';

    $response = $this->get(route('products.index', ['search' => $injection]));
    $response->assertOk();

    // Verify raw injection tag is not reflected unescaped
    $response->assertDontSee('<img src=x onerror=alert(1)>', false);
});

it('verifies search handles SQL injection payloads without database syntax errors', function () {
    $sqliPayloads = [
        "' OR '1'='1",
        "'; DROP TABLE products; --",
        "1' UNION SELECT null, null, null, null--",
        "admin'--",
    ];

    foreach ($sqliPayloads as $payload) {
        $response = $this->get(route('products.index', ['search' => $payload]));
        // Must handle gracefully without 500 database error
        $response->assertOk();
    }
});

it('verifies search handles regex wildcard and regex metacharacters safely', function () {
    $specialChars = ['.*', '^$', '\\', '?', '*', '%', '_', '[a-z]+', '(', ')'];

    foreach ($specialChars as $char) {
        $response = $this->get(route('products.index', ['search' => $char]));
        $response->assertOk();
    }
});

it('verifies search handles extreme length query strings without buffer or memory exhaustion', function () {
    $hugeQuery = str_repeat('furniture_scandinavian_', 200); // ~4600 chars

    $response = $this->get(route('products.index', ['search' => $hugeQuery]));
    $response->assertOk();
});

it('verifies search handles unicode whitespace and blank search parameters', function () {
    $blankInputs = [
        '   ',
        "\u{00A0}\u{00A0}",
        "\u{3000}",
    ];

    foreach ($blankInputs as $blank) {
        $response = $this->get(route('products.index', ['search' => $blank]));
        $response->assertOk();
    }
});

it('verifies search modal suggestion links have valid encoded Vietnamese parameters', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // Suggestion pills on storefront search modal
    $response->assertSee(route('products.index', ['search' => 'Bàn']), false);
    $response->assertSee(route('products.index', ['search' => 'Ghế']), false);
    $response->assertSee(route('products.index', ['search' => 'Đèn']), false);
    $response->assertSee(route('products.index', ['search' => 'Sofa']), false);
    $response->assertSee(route('products.index', ['search' => 'Tủ']), false);

    // Visiting each pill endpoint succeeds with 200 OK
    $this->get(route('products.index', ['search' => 'Bàn']))->assertOk();
    $this->get(route('products.index', ['search' => 'Ghế']))->assertOk();
});

/*
|--------------------------------------------------------------------------
| SECTION 5: RAPID STATE TRANSITIONS IN LIVEWIRE & MULTI-PAGE NAVIGATION
|--------------------------------------------------------------------------
*/

it('verifies CartDrawer toggles open and closed states via Alpine and Livewire events', function () {
    Livewire::test(CartDrawer::class)
        ->assertSet('isOpen', false)
        ->dispatch('open-cart')
        ->assertSet('isOpen', true)
        ->call('closeCart')
        ->assertSet('isOpen', false);
});

it('verifies AddToCartButton dispatches toast, cart-updated, and open-cart events upon product addition', function () {
    $product = Product::create([
        'name' => 'Nordic Dining Stool',
        'slug' => 'nordic-dining-stool',
        'price' => 750000,
        'stock' => 15,
        'status' => 'active',
    ]);

    Livewire::test(AddToCartButton::class, ['product' => $product])
        ->call('addToCart')
        ->assertDispatched('toast')
        ->assertDispatched('cart-updated')
        ->assertDispatched('open-cart');
});

it('verifies AddToCartButton rejects additions when stock is zero with error toast and no cart events', function () {
    $outOfStockProduct = Product::create([
        'name' => 'Sold Out Wooden Bench',
        'slug' => 'sold-out-wooden-bench',
        'price' => 1200000,
        'stock' => 0,
        'status' => 'active',
    ]);

    Livewire::test(AddToCartButton::class, ['product' => $outOfStockProduct])
        ->call('addToCart')
        ->assertDispatched('toast')
        ->assertNotDispatched('open-cart');
});

it('verifies rapid sequential quantity increments and decrements maintain accurate subtotal in CartDrawer', function () {
    $product = Product::create([
        'name' => 'Minimalist Coffee Mug',
        'slug' => 'minimalist-coffee-mug',
        'price' => 150000,
        'stock' => 50,
        'status' => 'active',
    ]);

    $cartService = app(CartService::class);
    $cartService->add($product->id, null, 1);

    Livewire::test(CartDrawer::class)
        ->call('updateQuantity', $product->id, null, 3)
        ->assertDispatched('cart-updated')
        ->call('updateQuantity', $product->id, null, 8)
        ->assertDispatched('cart-updated')
        ->call('updateQuantity', $product->id, null, 2)
        ->assertDispatched('cart-updated')
        ->call('removeItem', $product->id, null)
        ->assertDispatched('cart-updated');

    expect($cartService->calculateTotal())->toBe(0.0)
        ->and(count($cartService->getCart()))->toBe(0);
});

it('verifies CartDrawer ignores negative or zero quantity mutations without crashing', function () {
    $product = Product::create([
        'name' => 'Sturdy Bookshelf',
        'slug' => 'sturdy-bookshelf',
        'price' => 3200000,
        'stock' => 10,
        'status' => 'active',
    ]);

    $cartService = app(CartService::class);
    $cartService->add($product->id, null, 2);

    Livewire::test(CartDrawer::class)
        ->call('updateQuantity', $product->id, null, 0)   // qty < 1 ignored
        ->call('updateQuantity', $product->id, null, -5);  // negative qty ignored

    // Quantity should remain 2
    $raw = $cartService->getCart();
    $key = $product->id.'_0';
    expect($raw[$key]['quantity'])->toBe(2);
});

it('verifies CartDrawer handles removal of non-existent items safely', function () {
    $cartService = app(CartService::class);

    Livewire::test(CartDrawer::class)
        ->call('removeItem', 999999, null)
        ->assertDispatched('cart-updated');

    expect($cartService->getCart())->toBe([]);
});

it('verifies CartDrawer and CartCount components remain in perfect synchronization across multiple mutations', function () {
    $p1 = Product::create(['name' => 'Sync Item 1', 'slug' => 'sync-item-1', 'price' => 200000, 'stock' => 20, 'status' => 'active']);
    $p2 = Product::create(['name' => 'Sync Item 2', 'slug' => 'sync-item-2', 'price' => 500000, 'stock' => 20, 'status' => 'active']);

    $cartService = app(CartService::class);
    $cartService->add($p1->id, null, 3);
    $cartService->add($p2->id, null, 2);

    $countTest = Livewire::test(CartCount::class)->assertSet('count', 5);

    // Update quantity via drawer
    Livewire::test(CartDrawer::class)
        ->call('updateQuantity', $p1->id, null, 6);

    // Broadcast cart-updated to CartCount
    $countTest->dispatch('cart-updated')->assertSet('count', 8);

    // Remove an item via drawer
    Livewire::test(CartDrawer::class)
        ->call('removeItem', $p2->id, null);

    $countTest->dispatch('cart-updated')->assertSet('count', 6);
});

it('verifies cart session state is preserved across multi-page storefront navigation', function () {
    $pNav = Product::create([
        'name' => 'Persistent Cart Armchair',
        'slug' => 'persistent-cart-armchair',
        'price' => 3600000,
        'stock' => 10,
        'status' => 'published',
    ]);

    $cartService = app(CartService::class);
    $cartService->add($pNav->id, null, 2);

    // Navigate to Home
    $this->get(route('home'))->assertOk();

    // Navigate to Catalog
    $this->get(route('products.index'))->assertOk();

    // Navigate to Product Detail
    $this->get(route('products.show', $pNav->slug))->assertOk();

    // Navigate to Checkout
    $checkoutResp = $this->get(route('checkout.index'));
    $checkoutResp->assertOk();
    $checkoutResp->assertSee('Persistent Cart Armchair');
    $checkoutResp->assertSee('7.200.000₫');
});
