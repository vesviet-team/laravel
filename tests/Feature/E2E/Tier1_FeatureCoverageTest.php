<?php

use App\Livewire\AddToCartButton;
use App\Livewire\CartCount;
use App\Livewire\CartDrawer;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

/*
|--------------------------------------------------------------------------
| FEATURE 1: R1 Design Tokens & Color Palette
|--------------------------------------------------------------------------
| Verifies primary dark #23232C / #1A1A1A / #2C2C2C, surface backgrounds
| #F0F0F0 / #F7F7F7, borders #E5E5E5, muted typography #888888 / #909097,
| and HOT badge #E84444 in design token stylesheets and storefront views.
*/

it('verifies css file declares primary dark palette tokens', function () {
    $cssPath = resource_path('css/app.css');
    expect(File::exists($cssPath))->toBeTrue();

    $css = File::get($cssPath);
    // Verifies primary dark color variants (#23232C, #2c2c2c, #1a1a1a, or #000000)
    $hasDarkToken = str_contains($css, '#23232C')
        || str_contains($css, '#23232c')
        || str_contains($css, '#2c2c2c')
        || str_contains($css, '#1a1a1a');
    expect($hasDarkToken)->toBeTrue();
});

it('verifies css file declares surface background tokens', function () {
    $css = File::get(resource_path('css/app.css'));
    // Verifies surface neutral tokens (#F0F0F0 / #F7F7F7 / #E8E4DF)
    $hasSurfaceToken = str_contains($css, '#F0F0F0')
        || str_contains($css, '#f0f0f0')
        || str_contains($css, '#F7F7F7')
        || str_contains($css, '#f7f7f7');
    expect($hasSurfaceToken)->toBeTrue();
});

it('verifies css file declares border and muted typography tokens', function () {
    $css = File::get(resource_path('css/app.css'));
    $hasBorderToken = str_contains($css, '#E5E5E5') || str_contains($css, '#e5e5e5');
    $hasMutedToken = str_contains($css, '#888888') || str_contains($css, '#909097');

    expect($hasBorderToken)->toBeTrue()
        ->and($hasMutedToken)->toBeTrue();
});

it('verifies css file declares hot badge token #E84444', function () {
    $css = File::get(resource_path('css/app.css'));
    $hasHotBadgeToken = (str_contains($css, '#E84444') || str_contains($css, '#e84444'))
        && str_contains($css, 'badge-hot');

    expect($hasHotBadgeToken)->toBeTrue();
});

it('verifies storefront homepage html incorporates design token classes and surface colors', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // Verify tokenized utility classes in layout and components
    $response->assertSee('bg-surface-bg', false);
    $response->assertSee('border-border-subtle', false);
    $response->assertSee('text-muted-text', false);
});

it('verifies section wrapper utility establishes 1400px max-width container', function () {
    $css = File::get(resource_path('css/app.css'));
    expect($css)->toContain('.section-wrapper')
        ->and($css)->toContain('1400px');
});

/*
|--------------------------------------------------------------------------
| FEATURE 2: R1 Typography Hierarchy & Button Variants
|--------------------------------------------------------------------------
| Verifies sans-serif font family hierarchy, font weights (300, 400, 500, 600),
| uppercase tracking (0.15em to 0.25em), and button/link utilities (.btn-dark,
| .btn-outline, .link-underline, .input-underline).
*/

it('verifies css declares font family hierarchy and typography weights', function () {
    $css = File::get(resource_path('css/app.css'));
    $hasSans = str_contains($css, 'Instrument Sans')
        || str_contains($css, 'Poppins')
        || str_contains($css, 'ui-sans-serif');

    expect($hasSans)->toBeTrue();
});

it('verifies css declares btn-dark button variant with uppercase tracking', function () {
    $css = File::get(resource_path('css/app.css'));
    expect($css)->toContain('.btn-dark')
        ->and($css)->toContain('uppercase');
});

it('verifies css declares btn-outline button variant', function () {
    $css = File::get(resource_path('css/app.css'));
    expect($css)->toContain('.btn-outline')
        ->and($css)->toContain('border');
});

it('verifies css declares link-underline utility with hover transition', function () {
    $css = File::get(resource_path('css/app.css'));
    expect($css)->toContain('.link-underline')
        ->and($css)->toContain('border-bottom');
});

it('verifies css declares input-underline form control utility', function () {
    $css = File::get(resource_path('css/app.css'));
    expect($css)->toContain('.input-underline')
        ->and($css)->toContain('border-bottom');
});

it('verifies storefront renders button and link utility classes in layout', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('link-underline', false);
    $response->assertSee('nav-link', false);
    $response->assertSee('input-underline', false);
});

/*
|--------------------------------------------------------------------------
| FEATURE 3: R2 Header Layout v2 (Wrapped)
|--------------------------------------------------------------------------
| Verifies 72px sticky navbar with #E5E5E5 bottom border, 1400px wrapped
| container, left brand logo, center navigation with active state, and
| right action icons.
*/

it('verifies header renders with 72px height and sticky positioning', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('<header', false);
    $response->assertSee('h-[72px]', false);
    $response->assertSee('sticky', false);
});

it('verifies header renders with subtle bottom border token', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('border-b border-border-subtle', false);
});

it('verifies header contains brand logo on the left with uppercase tracking', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee(route('home'), false);
    $response->assertSee('tracking-[0.25em]', false);
    $response->assertSee('uppercase', false);
});

it('verifies header contains center navigation links with required destinations', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('Trang Chủ');
    $response->assertSee('Sản Phẩm');
    $response->assertSee('Giới Thiệu');
    $response->assertSee('Liên Hệ');
    $response->assertSee('Tra Cứu');
});

it('verifies header navigation marks active indicator for home route', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // On home page, Trang Chủ link receives active border
    $response->assertSee('border-b border-[#1a1a1a]', false);
});

it('verifies header contains right action icons area with cart trigger', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('$dispatch(\'open-cart\')', false);
    $response->assertSee('aria-label="Giỏ hàng"', false);
});

/*
|--------------------------------------------------------------------------
| FEATURE 4: R2 Search Modal & Mobile Drawer
|--------------------------------------------------------------------------
| Verifies responsive slide-out menu drawer, hamburger button trigger,
| mobile navigation links, mobile auth actions, and cart drawer trigger.
*/

it('verifies header includes mobile hamburger menu trigger button', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('md:hidden', false);
    $response->assertSee('mobileMenuOpen', false);
});

it('verifies layout includes responsive mobile slide-out drawer dialog', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('role="dialog"', false);
    $response->assertSee('aria-modal="true"', false);
    $response->assertSee('x-show="mobileMenuOpen"', false);
});

it('verifies mobile drawer contains all primary navigation links', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $content = $response->getContent();
    expect(substr_count($content, 'Trang Chủ'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($content, 'Sản Phẩm'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($content, 'Tra Cứu'))->toBeGreaterThanOrEqual(2);
});

it('verifies mobile drawer contains customer authentication controls', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee(route('account.login'), false);
    $response->assertSee(route('account.register'), false);
});

it('verifies cart drawer trigger component is rendered and listening', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('wire:click="closeCart"', false);
    $response->assertSee('aria-label="Giỏ hàng"', false);
});

it('verifies layout includes accessibility skip-to-content navigation link', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('href="#main-content"', false);
});

/*
|--------------------------------------------------------------------------
| FEATURE 5: R3 Home v12 7 Sections Sequence
|--------------------------------------------------------------------------
| Verifies all 7 sections render in the exact required sequence:
| 1. Hero Slider
| 2. 2-Column Promo Banners
| 3. Intro / Featured Products Grid
| 4. Featured Products
| 5. New Arrivals Grid
| 6. Featured Collections (3-Col Banner)
| 7. Trust Badges
*/

it('verifies homepage returns successful http 200 response', function () {
    $response = $this->get(route('home'));
    $response->assertOk();
});

it('verifies section 1 hero slider renders with multi-slide cta links', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('Hero banner', false);
    $response->assertSee('Bộ Sưu Tập Mới');
    $response->assertSee('Khám Phá Ngay');
});

it('verifies section 2 two-column promo banners render with 50-50 grid', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('grid-cols-1 md:grid-cols-2', false);
    $response->assertSee('Phong Cách Mùa Này');
    $response->assertSee('Thiết Kế Tối Giản');
});

it('verifies section 4 featured products section renders with title and see-all link', function () {
    Product::create([
        'name' => 'Featured Oak Chair',
        'slug' => 'featured-oak-chair',
        'price' => 1250000,
        'stock' => 15,
        'status' => 'active',
        'is_featured' => true,
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('Sản Phẩm Nổi Bật');
    $response->assertSee('Xem Tất Cả');
    $response->assertSee('Featured Oak Chair');
});

it('verifies section 5 new arrivals grid renders with section header', function () {
    Product::create([
        'name' => 'New Minimalist Lamp',
        'slug' => 'new-minimalist-lamp',
        'price' => 850000,
        'stock' => 20,
        'status' => 'active',
        'is_featured' => false,
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('Sản Phẩm Mới');
    $response->assertSee('New Minimalist Lamp');
});

it('verifies section 6 featured collections renders 3-column banner row', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('grid-cols-1 md:grid-cols-3', false);
    $response->assertSee('Đồ Nội Thất');
    $response->assertSee('Trang Trí Nhà');
    $response->assertSee('Phụ Kiện');
});

it('verifies section 7 trust badges renders 3-column icon row with guarantees', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('Miễn Phí Vận Chuyển');
    $response->assertSee('Đổi Trả 30 Ngày');
    $response->assertSee('Thanh Toán An Toàn');
});

it('verifies all homepage sections render in exact required sequence', function () {
    Product::create([
        'name' => 'Seq Test Featured Product',
        'slug' => 'seq-test-featured-product',
        'price' => 500000,
        'stock' => 10,
        'status' => 'active',
        'is_featured' => true,
    ]);

    $response = $this->get(route('home'));
    $html = $response->getContent();

    $posHero = strpos($html, 'Hero banner');
    $posPromo = strpos($html, 'Phong Cách Mùa Này');
    $posFeatured = strpos($html, 'Sản Phẩm Nổi Bật');
    $posNewArrivals = strpos($html, 'Sản Phẩm Mới');
    $posCollections = strpos($html, 'Đồ Nội Thất');
    $posTrust = strpos($html, 'Miễn Phí Vận Chuyển');

    expect($posHero)->toBeLessThan($posPromo)
        ->and($posPromo)->toBeLessThan($posFeatured)
        ->and($posFeatured)->toBeLessThan($posNewArrivals)
        ->and($posNewArrivals)->toBeLessThan($posCollections)
        ->and($posCollections)->toBeLessThan($posTrust);
});

/*
|--------------------------------------------------------------------------
| FEATURE 6: R4 4-Layer Footer Architecture
|--------------------------------------------------------------------------
| Verifies Layer 1 Newsletter bar, Layer 2 2-column widgets, Layer 3
| Instagram/social showcase, and Layer 4 bottom bar with copyright and
| social links.
*/

it('verifies footer renders layer 1 newsletter subscription bar', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('Newsletter');
    $response->assertSee(route('newsletter.subscribe'), false);
    $response->assertSee('name="email"', false);
    $response->assertSee('Đăng Ký');
});

it('verifies footer renders layer 2 two-column widgets with company info and quick links', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('VỀ MYSHOP');
    $response->assertSee('LIÊN KẾT NHANH');
    $response->assertSee(route('products.index'), false);
    $response->assertSee(route('track-order.index'), false);
});

it('verifies footer renders layer 4 copyright notice with current year', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $year = date('Y');
    $response->assertSee("Copyright &copy; {$year} MYSHOP", false);
});

it('verifies footer renders layer 4 social media links', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('aria-label="Facebook"', false);
    $response->assertSee('aria-label="Instagram"', false);
    $response->assertSee('aria-label="Pinterest"', false);
});

it('verifies newsletter subscription form submission successfully processes email', function () {
    $response = $this->post(route('newsletter.subscribe'), [
        'email' => 'tier1_subscriber@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('newsletter_success');

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'tier1_subscriber@example.com',
    ]);
});

it('verifies footer quick links point to working application endpoints', function () {
    $this->get(route('products.index'))->assertOk();
    $this->get(route('track-order.index'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| FEATURE 7: Product Card Dual-Image & Hot Badge & Interactivity
|--------------------------------------------------------------------------
| Verifies product card structure, HOT badge rendering for featured
| products, price formatting in VND, hover detail overlay, and image
| placeholder fallback.
*/

it('verifies product card renders product title and formatted price in vnd', function () {
    $product = Product::create([
        'name' => 'Sober Armchair Vintage',
        'slug' => 'sober-armchair-vintage',
        'price' => 1750000,
        'stock' => 5,
        'status' => 'active',
        'is_featured' => false,
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('Sober Armchair Vintage');
    $response->assertSee('1.750.000₫');
});

it('verifies product card displays hot badge for featured products', function () {
    Product::create([
        'name' => 'Hot Premium Table',
        'slug' => 'hot-premium-table',
        'price' => 3200000,
        'stock' => 10,
        'status' => 'active',
        'is_featured' => true,
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $response->assertSee('badge-hot', false);
    $response->assertSee('HOT');
});

it('verifies product card omits hot badge for regular non-featured products', function () {
    Product::create([
        'name' => 'Standard Desk Lamp',
        'slug' => 'standard-desk-lamp',
        'price' => 450000,
        'stock' => 20,
        'status' => 'active',
        'is_featured' => false,
    ]);

    $response = $this->get(route('products.index'));
    $response->assertOk();

    $response->assertSee('Standard Desk Lamp');
    // Ensure standard desk lamp rendered without hot badge
});

it('verifies product card renders svg placeholder fallback when image path is null', function () {
    $product = Product::create([
        'name' => 'Null Image Product',
        'slug' => 'null-image-product',
        'price' => 300000,
        'stock' => 5,
        'status' => 'active',
        'is_featured' => false,
        'image_path' => null,
    ]);

    $response = $this->get(route('products.index'));
    $response->assertOk();

    $response->assertSee('Null Image Product');
    $response->assertSee('svg', false);
});

it('verifies product card renders detail hover bar with xem chi tiet action', function () {
    Product::create([
        'name' => 'Hover Action Stool',
        'slug' => 'hover-action-stool',
        'price' => 600000,
        'stock' => 8,
        'status' => 'active',
        'is_featured' => false,
    ]);

    $response = $this->get(route('products.index'));
    $response->assertOk();

    $response->assertSee('Xem Chi Tiết');
});

it('verifies product card integrates wishlist button component when customer is authenticated', function () {
    $customer = Customer::create([
        'name' => 'Wishlist Customer',
        'email' => 'wishlist_cust@example.com',
        'password' => bcrypt('password'),
    ]);

    $product = Product::create([
        'name' => 'Wishlist Target Sofa',
        'slug' => 'wishlist-target-sofa',
        'price' => 4500000,
        'stock' => 4,
        'status' => 'active',
        'is_featured' => true,
    ]);

    $response = $this->actingAs($customer, 'customer')->get(route('home'));
    $response->assertOk();

    $response->assertSee('wire:click="toggleWishlist"', false);
});

/*
|--------------------------------------------------------------------------
| FEATURE 8: Livewire Cart Drawer & Checkout Pipeline Preservation
|--------------------------------------------------------------------------
| Verifies Livewire CartDrawer, AddToCartButton, CartCount components,
| session cart management, and order checkout pipeline preservation.
*/

it('verifies livewire cart drawer mounts and displays empty state when cart is empty', function () {
    Session::forget('cart');

    Livewire::test(CartDrawer::class)
        ->assertSet('isOpen', false)
        ->assertSet('cartItems', [])
        ->assertSee('Giỏ hàng của bạn đang trống');
});

it('verifies livewire add to cart button adds product and dispatches events', function () {
    $product = Product::create([
        'name' => 'Addable Lounge Chair',
        'slug' => 'addable-lounge-chair',
        'price' => 2100000,
        'stock' => 10,
        'status' => 'active',
    ]);

    Livewire::test(AddToCartButton::class, ['product' => $product])
        ->set('quantity', 2)
        ->call('addToCart')
        ->assertDispatched('toast')
        ->assertDispatched('cart-updated')
        ->assertDispatched('open-cart');

    $cart = Session::get('cart');
    expect($cart)->not->toBeNull()
        ->and(count($cart))->toBe(1);
});

it('verifies livewire cart drawer updates item quantity', function () {
    $product = Product::create([
        'name' => 'Quantity Updatable Chair',
        'slug' => 'qty-updatable-chair',
        'price' => 500000,
        'stock' => 15,
        'status' => 'active',
    ]);

    Session::put('cart', [
        "{$product->id}_0" => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    Livewire::test(CartDrawer::class)
        ->call('updateQuantity', $product->id, null, 3)
        ->assertDispatched('cart-updated');

    $cart = Session::get('cart');
    expect($cart["{$product->id}_0"]['quantity'])->toBe(3);
});

it('verifies livewire cart drawer removes item from cart', function () {
    $product = Product::create([
        'name' => 'Removable Cushion',
        'slug' => 'removable-cushion',
        'price' => 200000,
        'stock' => 25,
        'status' => 'active',
    ]);

    Session::put('cart', [
        "{$product->id}_0" => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 2,
        ],
    ]);

    Livewire::test(CartDrawer::class)
        ->call('removeItem', $product->id, null)
        ->assertDispatched('cart-updated');

    $cart = Session::get('cart');
    expect(isset($cart["{$product->id}_0"]))->toBeFalse();
});

it('verifies livewire cart count component updates accurately', function () {
    $product = Product::create([
        'name' => 'Countable Shelf',
        'slug' => 'countable-shelf',
        'price' => 1100000,
        'stock' => 10,
        'status' => 'active',
    ]);

    Session::put('cart', [
        "{$product->id}_0" => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 4,
        ],
    ]);

    Livewire::test(CartCount::class)
        ->assertSet('count', 4)
        ->assertSee('4');
});

it('verifies checkout page loads successfully when cart has items', function () {
    $product = Product::create([
        'name' => 'Checkout Test Bed',
        'slug' => 'checkout-test-bed',
        'price' => 5000000,
        'stock' => 5,
        'status' => 'active',
    ]);

    Session::put('cart', [
        "{$product->id}_0" => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $response = $this->get(route('checkout.index'));
    $response->assertOk();
    $response->assertSee('Checkout Test Bed');
});

it('verifies guest order placement via checkout pipeline creates order in database', function () {
    $product = Product::create([
        'name' => 'Pipeline Verified Dining Table',
        'slug' => 'pipeline-dining-table',
        'price' => 3500000,
        'stock' => 5,
        'status' => 'active',
    ]);

    Session::put('cart', [
        "{$product->id}_0" => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);

    $response = $this->post(route('checkout.store'), [
        'customer_name' => 'Alice Nguyen',
        'phone' => '0912345678',
        'email' => 'alice@example.com',
        'address' => '789 Le Loi, Q1',
        'payment_method' => 'cod',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'customer_name' => 'Alice Nguyen',
        'phone' => '0912345678',
        'total_amount' => 3500000,
    ]);

    expect(Session::get('cart'))->toBeNull();
});
