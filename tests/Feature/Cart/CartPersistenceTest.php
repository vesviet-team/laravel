<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerCartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::create(['name' => 'Test', 'slug' => 'test']);

    $this->product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-001',
        'price' => 200000,
        'stock' => 50,
        'category_id' => $this->category->id,
        'status' => 'published',
    ]);

    $this->customer = Customer::factory()->create();
    $this->cartService = app(CartService::class);
});

// -----------------------------------------------------------------------------
// CP-01: Guest add ? Login ? Cart preserved and merged
// -----------------------------------------------------------------------------

test('CP-01: guest cart is merged to DB on login', function () {
    // Guest adds to cart via session
    $this->cartService->add($this->product->id, null, 2);

    $guestCart = Session::get('cart', []);
    expect($guestCart)->not->toBeEmpty();

    // Simulate login merge
    $this->cartService->mergeGuestCartToDB($this->customer, $guestCart);

    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(1);
    expect(CustomerCartItem::where('customer_id', $this->customer->id)->first()->quantity)->toBe(2);
});

// -----------------------------------------------------------------------------
// CP-02: Guest cart empty ? merge ? no DB rows created
// -----------------------------------------------------------------------------

test('CP-02: merging empty guest cart creates no DB rows', function () {
    $this->cartService->mergeGuestCartToDB($this->customer, []);

    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(0);
});

// -----------------------------------------------------------------------------
// CP-03: Merge qty additive
// -----------------------------------------------------------------------------

test('CP-03: merge adds qty from guest to existing DB qty', function () {
    // Pre-seed DB with qty 2
    CustomerCartItem::create([
        'customer_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'product_variant_id' => 0, // sentinel (no variant)
        'quantity' => 2,
        'updated_at' => now(),
    ]);

    $productKey = $this->product->id.'_0';
    $guestCart = [$productKey => ['product_id' => $this->product->id, 'product_variant_id' => null, 'quantity' => 1]];

    $this->cartService->mergeGuestCartToDB($this->customer, $guestCart);

    $row = CustomerCartItem::where('customer_id', $this->customer->id)->first();
    expect($row->quantity)->toBe(3); // 2 + 1
});

// -----------------------------------------------------------------------------
// CP-04: Merge qty soft cap at 99
// -----------------------------------------------------------------------------

test('CP-04: merge caps qty at 99 when sum exceeds limit', function () {
    CustomerCartItem::create([
        'customer_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'product_variant_id' => 0, // sentinel (no variant)
        'quantity' => 98,
        'updated_at' => now(),
    ]);

    $productKey = $this->product->id.'_0';
    $guestCart = [$productKey => ['product_id' => $this->product->id, 'product_variant_id' => null, 'quantity' => 5]];

    $this->cartService->mergeGuestCartToDB($this->customer, $guestCart);

    $row = CustomerCartItem::where('customer_id', $this->customer->id)->first();
    expect($row->quantity)->toBe(99); // capped
});

// -----------------------------------------------------------------------------
// CP-05: Logged-in add() syncs to DB
// -----------------------------------------------------------------------------

test('CP-05: add() syncs to DB when customer is logged in', function () {
    Auth::guard('customer')->login($this->customer);

    $this->cartService->add($this->product->id, null, 3);

    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(1);
    expect(CustomerCartItem::where('customer_id', $this->customer->id)->first()->quantity)->toBe(3);
});

// -----------------------------------------------------------------------------
// CP-06: Logged-in update() syncs to DB
// -----------------------------------------------------------------------------

test('CP-06: update() qty syncs to DB', function () {
    Auth::guard('customer')->login($this->customer);

    $this->cartService->add($this->product->id, null, 1);
    $this->cartService->update($this->product->id, null, 5);

    expect(CustomerCartItem::where('customer_id', $this->customer->id)->first()->quantity)->toBe(5);
});

// -----------------------------------------------------------------------------
// CP-07: Logged-in remove() deletes DB row
// -----------------------------------------------------------------------------

test('CP-07: remove() deletes DB row', function () {
    Auth::guard('customer')->login($this->customer);

    $this->cartService->add($this->product->id, null, 2);
    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(1);

    $this->cartService->remove($this->product->id, null);
    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(0);
});

// -----------------------------------------------------------------------------
// CP-08: clear() removes both DB rows and session
// -----------------------------------------------------------------------------

test('CP-08: clear() empties DB and session', function () {
    Auth::guard('customer')->login($this->customer);

    $this->cartService->add($this->product->id, null, 2);

    $this->cartService->clear();

    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(0);
    expect(Session::get('cart', []))->toBeEmpty();
});

// -----------------------------------------------------------------------------
// CP-09: Session expired ? getCart() reloads from DB
// -----------------------------------------------------------------------------

test('CP-09: getCart() reloads from DB when session is empty', function () {
    Auth::guard('customer')->login($this->customer);

    // Seed DB directly (simulate pre-existing cart from another device)
    CustomerCartItem::create([
        'customer_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'product_variant_id' => 0, // sentinel (no variant)
        'quantity' => 4,
        'updated_at' => now(),
    ]);

    // Ensure session is empty (simulate session expiry)
    Session::forget('cart');

    $cart = $this->cartService->getCart();

    expect($cart)->not->toBeEmpty();
    $key = $this->product->id.'_0';
    expect($cart[$key]['quantity'])->toBe(4);
    // Session should now be warmed
    expect(Session::get('cart', []))->not->toBeEmpty();
});

// -----------------------------------------------------------------------------
// CP-10: Cross-device sync — add on device A, load on device B (fresh session)
// -----------------------------------------------------------------------------

test('CP-10: cart synced across devices via DB', function () {
    Auth::guard('customer')->login($this->customer);

    // Device A: add item ? synced to DB
    $this->cartService->add($this->product->id, null, 2);
    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(1);

    // Device B: fresh session (simulate new browser session)
    Session::forget('cart');

    $cart = $this->cartService->getCart();

    expect($cart)->not->toBeEmpty();
    $key = $this->product->id.'_0';
    expect($cart[$key]['quantity'])->toBe(2);
});

// -----------------------------------------------------------------------------
// CP-11: Variant cleanup — 2 variants of same product, remove one only
// -----------------------------------------------------------------------------

test('CP-11: removing one variant does not delete other variants of same product', function () {
    Auth::guard('customer')->login($this->customer);

    $product2 = Product::create([
        'name' => 'Variant Product',
        'slug' => 'variant-product',
        'sku' => 'VAR-001',
        'price' => 150000,
        'stock' => 20,
        'category_id' => $this->category->id,
        'status' => 'published',
    ]);

    // Add same product with 2 different variant IDs (mocked via direct DB seed)
    CustomerCartItem::create([
        'customer_id' => $this->customer->id,
        'product_id' => $product2->id,
        'product_variant_id' => 1, // size M
        'quantity' => 1,
        'updated_at' => now(),
    ]);
    CustomerCartItem::create([
        'customer_id' => $this->customer->id,
        'product_id' => $product2->id,
        'product_variant_id' => 2, // size L
        'quantity' => 1,
        'updated_at' => now(),
    ]);

    // Warm session with both variants
    $this->cartService->getCart(); // reload from DB

    // Remove variant 1 (size M)
    $this->cartService->remove($product2->id, 1);

    // Variant 2 (size L) must still exist
    expect(CustomerCartItem::where('customer_id', $this->customer->id)
        ->where('product_variant_id', 2)
        ->count())->toBe(1);
});

// -----------------------------------------------------------------------------
// CP-12: Customer isolation - Customer A cannot read Customer B's cart
// -----------------------------------------------------------------------------

test('CP-12: customer isolation - getCart() returns only own items', function () {
    // Customer A adds items
    Auth::guard('customer')->login($this->customer);
    $this->cartService->add($this->product->id, null, 3);

    // Seed DB directly for Customer B
    $customerB = Customer::create([
        'name' => 'Customer B',
        'email' => 'b@test.com',
        'password' => bcrypt('secret'),
        'status' => 'active',
    ]);
    CustomerCartItem::create([
        'customer_id' => $customerB->id,
        'product_id' => $this->product->id,
        'product_variant_id' => 0,
        'quantity' => 99,
        'updated_at' => now(),
    ]);

    // A's cart must only contain A's items
    $cartA = $this->cartService->getCart();
    $key = $this->product->id.'_0';
    expect($cartA[$key]['quantity'])->toBe(3); // not 99 from B
    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(1);
    expect(CustomerCartItem::where('customer_id', $customerB->id)->count())->toBe(1);
});

// -----------------------------------------------------------------------------
// CP-13: 2FA login - mergeGuestCartToDB called in verifyTwoFactor path
// -----------------------------------------------------------------------------

test('CP-13: mergeGuestCartToDB called correctly after 2FA completes', function () {
    // Simulate: guest added items to session before login
    $guestCart = [$this->product->id.'_0' => [
        'product_id' => $this->product->id,
        'product_variant_id' => null,
        'quantity' => 2,
    ]];

    // Call mergeGuestCartToDB directly (simulating what verifyTwoFactor now does)
    $this->cartService->mergeGuestCartToDB($this->customer, $guestCart);

    expect(CustomerCartItem::where('customer_id', $this->customer->id)->count())->toBe(1);
    expect(CustomerCartItem::where('customer_id', $this->customer->id)->first()->quantity)->toBe(2);
});

// -----------------------------------------------------------------------------
// CP-14: add() qty cap at 99
// -----------------------------------------------------------------------------

test('CP-14: add() caps total qty at 99', function () {
    Auth::guard('customer')->login($this->customer);

    // Add 98 first
    $this->cartService->add($this->product->id, null, 98);
    // Then add 5 more — should be capped at 99, not 103
    $this->cartService->add($this->product->id, null, 5);

    expect(CustomerCartItem::where('customer_id', $this->customer->id)->first()->quantity)->toBe(99);
});
