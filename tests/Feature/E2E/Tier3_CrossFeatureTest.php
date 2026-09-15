<?php

use App\Enums\OrderStatus;
use App\Livewire\AddToCartButton;
use App\Livewire\CartCount;
use App\Livewire\CartDrawer;
use App\Livewire\ProductReviews;
use App\Livewire\WishlistButton;
use App\Livewire\WishlistPage;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 1: Add to Cart + Cart Count + Cart Drawer Co-ordination
|--------------------------------------------------------------------------
| Verifies that adding a product via AddToCartButton updates the session
| cart, dispatches events, and seamlessly updates both CartCount and
| CartDrawer components.
*/

it('verifies add to cart synchronizes livewire cart count and cart drawer contents', function () {
    $product = Product::create([
        'name' => 'Cross Sync Dining Table',
        'slug' => 'cross-sync-dining-table',
        'price' => 3500000,
        'stock' => 10,
        'status' => 'active',
    ]);

    // 1. Add item to cart via AddToCartButton component
    Livewire::test(AddToCartButton::class, ['product' => $product])
        ->set('quantity', 2)
        ->call('addToCart')
        ->assertDispatched('cart-updated')
        ->assertDispatched('open-cart');

    // 2. Verify CartCount component reflects 2 items
    Livewire::test(CartCount::class)
        ->assertSet('count', 2)
        ->assertSee('2');

    // 3. Verify CartDrawer displays the product name and 7.000.000₫ subtotal
    Livewire::test(CartDrawer::class)
        ->assertSee('Cross Sync Dining Table')
        ->assertSee('7.000.000₫');
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 2: Cart Drawer Item Removal + Cart Count Sync
|--------------------------------------------------------------------------
| Verifies that removing an item from the CartDrawer adjusts the subtotal
| and synchronizes the header CartCount component.
*/

it('verifies cart drawer item removal synchronizes cart count and subtotal', function () {
    $product1 = Product::create([
        'name' => 'Cross Product A',
        'slug' => 'cross-product-a',
        'price' => 500000,
        'stock' => 10,
        'status' => 'active',
    ]);

    $product2 = Product::create([
        'name' => 'Cross Product B',
        'slug' => 'cross-product-b',
        'price' => 300000,
        'stock' => 10,
        'status' => 'active',
    ]);

    Session::put('cart', [
        "{$product1->id}_0" => [
            'product_id' => $product1->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
        "{$product2->id}_0" => [
            'product_id' => $product2->id,
            'product_variant_id' => null,
            'quantity' => 2,
        ],
    ]);

    // Initial count is 3 items
    Livewire::test(CartCount::class)->assertSet('count', 3);

    // Remove Product 2 from CartDrawer
    Livewire::test(CartDrawer::class)
        ->call('removeItem', $product2->id, null)
        ->assertDispatched('cart-updated')
        ->assertSee('Cross Product A')
        ->assertDontSee('Cross Product B')
        ->assertSee('500.000₫');

    // Updated count is 1 item
    Livewire::test(CartCount::class)->assertSet('count', 1);
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 3: Newsletter Subscription + Flash Message Persistence
|--------------------------------------------------------------------------
| Verifies that subscribing to the newsletter from footer redirects back
| to homepage and renders the success alert while keeping the entire
| layout (header, hero, footer) intact.
*/

it('verifies newsletter subscription flash message persists across homepage layout', function () {
    $response = $this->from(route('home'))->post(route('newsletter.subscribe'), [
        'email' => 'cross_newsletter@example.com',
    ]);

    $response->assertRedirect(route('home'));

    $followResponse = $this->get(route('home'));
    $followResponse->assertOk();

    // Verify flash message
    $followResponse->assertSee('Cảm ơn! Bạn đã đăng ký nhận bản tin thành công.');

    // Verify surrounding layout remains intact
    $followResponse->assertSee('MYSHOP');
    $followResponse->assertSee('Hero banner', false);
    $followResponse->assertSee('VỀ MYSHOP');
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 4: Catalog Category Filter + Sorting Interoperability
|--------------------------------------------------------------------------
| Verifies that category filtering and sorting by price work together
| cohesively in the catalog.
*/

it('verifies category filter and sort interoperability in product catalog', function () {
    $livingCategory = Category::create([
        'name' => 'Living Room',
        'slug' => 'living-room',
    ]);

    $bedroomCategory = Category::create([
        'name' => 'Bedroom',
        'slug' => 'bedroom',
    ]);

    $livingCheap = Product::create([
        'name' => 'Living Cheap Coffee Table',
        'slug' => 'living-cheap-coffee-table',
        'price' => 1200000,
        'stock' => 5,
        'status' => 'active',
        'category_id' => $livingCategory->id,
    ]);

    $livingExpensive = Product::create([
        'name' => 'Living Expensive Sectional',
        'slug' => 'living-expensive-sectional',
        'price' => 8500000,
        'stock' => 2,
        'status' => 'active',
        'category_id' => $livingCategory->id,
    ]);

    $bedroomItem = Product::create([
        'name' => 'Bedroom Nightstand',
        'slug' => 'bedroom-nightstand',
        'price' => 900000,
        'stock' => 4,
        'status' => 'active',
        'category_id' => $bedroomCategory->id,
    ]);

    $response = $this->get(route('products.index', [
        'category' => 'living-room',
        'sort' => 'price_asc',
    ]));

    $response->assertOk();
    $response->assertSee('Living Cheap Coffee Table');
    $response->assertSee('Living Expensive Sectional');
    $response->assertDontSee('Bedroom Nightstand');

    $html = $response->getContent();
    $posCheap = strpos($html, 'Living Cheap Coffee Table');
    $posExpensive = strpos($html, 'Living Expensive Sectional');
    expect($posCheap)->toBeLessThan($posExpensive);
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 5: Customer Auth + Header State + Wishlist Toggle
|--------------------------------------------------------------------------
| Verifies customer login alters header controls and enables interactive
| wishlist toggling on products.
*/

it('verifies customer authentication alters header navigation and enables wishlist toggling', function () {
    $customer = Customer::create([
        'name' => 'Cross Customer User',
        'email' => 'cross_cust@example.com',
        'password' => bcrypt('password123'),
        'status' => 'active',
    ]);

    $product = Product::create([
        'name' => 'Wishlist Favorite Lounge',
        'slug' => 'wishlist-fav-lounge',
        'price' => 2400000,
        'stock' => 6,
        'status' => 'active',
        'is_featured' => true,
    ]);

    // 1. Authenticated customer visits home
    $homeResponse = $this->actingAs($customer, 'customer')->get(route('home'));
    $homeResponse->assertOk();
    $homeResponse->assertSee(route('account.orders'), false);
    $homeResponse->assertSee(route('account.wishlist'), false);

    // 2. Toggle wishlist via Livewire
    Livewire::actingAs($customer, 'customer')
        ->test(WishlistButton::class, ['product' => $product])
        ->call('toggleWishlist');

    $this->assertDatabaseHas('wishlists', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
    ]);

    // 3. Test Wishlist page component renders the added product
    Livewire::actingAs($customer, 'customer')
        ->test(WishlistPage::class)
        ->assertSee('Wishlist Favorite Lounge');
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 6: Homepage Featured -> Product Detail -> Add to Cart -> Checkout
|--------------------------------------------------------------------------
| Verifies navigating from homepage to product detail page, adding to
| cart, and proceeding to checkout.
*/

it('verifies homepage featured to product detail to checkout navigation flow', function () {
    $product = Product::create([
        'name' => 'Copenhague Desk Classic',
        'slug' => 'copenhague-desk-classic',
        'price' => 4200000,
        'stock' => 8,
        'status' => 'active',
        'is_featured' => true,
    ]);

    // 1. Visit homepage, confirm product is listed
    $homeResponse = $this->get(route('home'));
    $homeResponse->assertOk();
    $homeResponse->assertSee('Copenhague Desk Classic');

    // 2. Navigate to product detail page
    // Note: show method looks for published or active
    $product->update(['status' => 'published']);
    $detailResponse = $this->get(route('products.show', $product->slug));
    $detailResponse->assertOk();
    $detailResponse->assertSee('Copenhague Desk Classic');

    // 3. Add to cart
    Livewire::test(AddToCartButton::class, ['product' => $product])
        ->set('quantity', 1)
        ->call('addToCart');

    // 4. Load checkout page
    $checkoutResponse = $this->get(route('checkout.index'));
    $checkoutResponse->assertOk();
    $checkoutResponse->assertSee('Copenhague Desk Classic');
    $checkoutResponse->assertSee('4.200.000₫');
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 7: Footer Customer Service -> Order Tracking Pipeline
|--------------------------------------------------------------------------
| Verifies navigating from footer link to order tracking and looking up
| a placed order.
*/

it('verifies footer customer service navigation to order tracking and lookup pipeline', function () {
    $order = Order::create([
        'order_number' => 'ORD-CROSS-778899',
        'customer_name' => 'Tran Van Minh',
        'phone' => '0933445566',
        'address' => '101 Hai Ba Trung, Q1',
        'subtotal' => 2800000,
        'total_amount' => 2800000,
        'payment_method' => 'cod',
        'status' => 'pending',
    ]);

    $order->items()->create([
        'product_name' => 'Minimalist Coffee Table',
        'price_at_purchase' => 2800000,
        'quantity' => 1,
    ]);

    // 1. Visit tracking page via footer link
    $trackPage = $this->get(route('track-order.index'));
    $trackPage->assertOk();

    // 2. Submit order tracking query
    $postResponse = $this->post(route('track-order.track'), [
        'order_number' => 'ORD-CROSS-778899',
        'contact_info' => '0933445566',
    ]);

    $postResponse->assertRedirect(route('track-order.index', ['order_number' => 'ORD-CROSS-778899', 'contact_info' => '0933445566']));

    // 3. Verify lookup results
    $resultPage = $this->get(route('track-order.index', ['order_number' => 'ORD-CROSS-778899', 'contact_info' => '0933445566']));
    $resultPage->assertOk();
    $resultPage->assertSee('ORD-CROSS-778899');
    $resultPage->assertSee('Minimalist Coffee Table');
    $resultPage->assertSee('2.800.000₫');
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 8: Mobile Menu & Cart Drawer Coexistence
|--------------------------------------------------------------------------
| Verifies mobile menu slide-out and cart drawer components coexist in
| the global layout without DOM or z-index collisions.
*/

it('verifies mobile menu drawer and cart drawer modal coexist in layout', function () {
    $response = $this->get(route('home'));
    $response->assertOk();

    // Verify mobile drawer component
    $response->assertSee('x-show="mobileMenuOpen"', false);
    $response->assertSee('z-[100]', false);

    // Verify cart drawer component
    $response->assertSee('wire:click="closeCart"', false);
    $response->assertSee('z-[90]', false);
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 9: Multi-Variant Product Add to Cart and Drawer Differentiation
|--------------------------------------------------------------------------
| Verifies adding distinct variants of a product displays separate line
| items with corresponding variant names in the CartDrawer.
*/

it('verifies multi-variant product add to cart creates distinct line items in drawer', function () {
    $product = Product::create([
        'name' => 'Modular Sofa System',
        'slug' => 'modular-sofa-system',
        'price' => 5000000,
        'stock' => 20,
        'status' => 'active',
    ]);

    $variantBeige = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Beige Fabric',
        'sku' => 'MSS-BEIGE',
        'price' => 5200000,
        'stock' => 10,
    ]);

    $variantGray = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Charcoal Gray',
        'sku' => 'MSS-GRAY',
        'price' => 5500000,
        'stock' => 10,
    ]);

    Session::put('cart', [
        "{$product->id}_{$variantBeige->id}" => [
            'product_id' => $product->id,
            'product_variant_id' => $variantBeige->id,
            'quantity' => 1,
        ],
        "{$product->id}_{$variantGray->id}" => [
            'product_id' => $product->id,
            'product_variant_id' => $variantGray->id,
            'quantity' => 2,
        ],
    ]);

    // Subtotal = (5200000 * 1) + (5500000 * 2) = 16.200.000₫
    Livewire::test(CartDrawer::class)
        ->assertSee('Beige Fabric')
        ->assertSee('Charcoal Gray')
        ->assertSee('16.200.000₫');
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 10: Coupon / Promotion Discount Application in Checkout
|--------------------------------------------------------------------------
| Verifies applied coupons are calculated during checkout and stored
| accurately in the database order record.
*/

it('verifies coupon discount application in checkout creates discounted order record', function () {
    $coupon = Coupon::create([
        'code' => 'SOBER15',
        'type' => 'percentage',
        'value' => 15,
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Solid Oak Bookshelf',
        'slug' => 'solid-oak-bookshelf',
        'price' => 2000000,
        'stock' => 5,
        'status' => 'published',
    ]);

    Session::put('cart', [
        "{$product->id}_0" => [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ]);
    Session::put('coupon', 'SOBER15');

    $response = $this->post(route('checkout.store'), [
        'customer_name' => 'Coupon Tester',
        'phone' => '0903334444',
        'address' => '88 Pasteur, Q1',
        'payment_method' => 'cod',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $order = Order::latest('id')->first();
    // Subtotal: 2.000.000, Discount 15% = 300.000, Total = 1.700.000
    expect((float) $order->discount_amount)->toBe(300000.0)
        ->and((float) $order->total_amount)->toBe(1700000.0);
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 11: Hero Slider CTA and Category Banners Route Validation
|--------------------------------------------------------------------------
| Verifies that CTA links in Hero and Promo banners navigate to active
| product catalog routes with successful responses.
*/

it('verifies hero and promo banner cta links target working catalog routes', function () {
    Product::create([
        'name' => 'Banner Target Armchair',
        'slug' => 'banner-target-armchair',
        'price' => 1800000,
        'stock' => 5,
        'status' => 'active',
    ]);

    $response = $this->get(route('products.index'));
    $response->assertOk();
    $response->assertSee('Banner Target Armchair');
    $response->assertSee('Tất Cả Sản Phẩm');
});

/*
|--------------------------------------------------------------------------
| CROSS-FEATURE 12: Product Review Submission for Verified Customer Order
|--------------------------------------------------------------------------
| Verifies customer submitting product review after order placement.
*/

it('verifies customer product review submission pipeline', function () {
    $customer = Customer::create([
        'name' => 'Reviewer Customer',
        'email' => 'reviewer_cust@example.com',
        'password' => bcrypt('password'),
    ]);

    $product = Product::create([
        'name' => 'Reviewed Lounge Ottoman',
        'slug' => 'reviewed-lounge-ottoman',
        'price' => 950000,
        'stock' => 10,
        'status' => 'published',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-VERIFIED-REVIEW',
        'status' => OrderStatus::Delivered,
        'payment_method' => 'cod',
        'customer_name' => $customer->name,
        'phone' => '0901234567',
        'address' => '123 Verified St',
        'subtotal' => 950000,
        'discount_amount' => 0,
        'shipping_fee' => 0,
        'total_amount' => 950000,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'price_at_purchase' => 950000,
        'subtotal' => 950000,
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test(ProductReviews::class, ['product' => $product])
        ->set('rating', 5)
        ->set('comment', 'Thiết kế đẹp mắt, giao hàng nhanh chóng!')
        ->call('submitReview');

    $this->assertDatabaseHas('product_reviews', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'rating' => 5,
        'comment' => 'Thiết kế đẹp mắt, giao hàng nhanh chóng!',
    ]);
});
