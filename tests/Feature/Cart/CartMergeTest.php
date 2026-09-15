<?php

namespace Tests\Feature\Cart;

use App\Models\Customer;
use App\Models\CustomerCartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    Session::start();
    $this->service = app(CartService::class);
    $this->customer = Customer::factory()->create();

    $this->productA = Product::create([
        'name' => 'Product A', 'slug' => 'product-a',
        'price' => 100_000, 'stock' => 50, 'status' => 'published',
    ]);
    $this->productB = Product::create([
        'name' => 'Product B', 'slug' => 'product-b',
        'price' => 200_000, 'stock' => 20, 'status' => 'published',
    ]);
});

// ── mergeGuestCartToDB ───────────────────────────────────────────────────────

it('merges guest cart items into db cart on login', function () {
    $guestCart = [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2,
        ],
    ];

    $this->service->mergeGuestCartToDB($this->customer, $guestCart);

    $item = CustomerCartItem::where('customer_id', $this->customer->id)
        ->where('product_id', $this->productA->id)
        ->first();

    expect($item)->not->toBeNull()
        ->and($item->quantity)->toBe(2);
});

it('sums quantities when db cart already has same item', function () {
    // Pre-existing DB item: 3
    CustomerCartItem::create([
        'customer_id' => $this->customer->id,
        'product_id' => $this->productA->id,
        'product_variant_id' => 0,
        'quantity' => 3,
    ]);

    $guestCart = [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 4, // should merge to 7
        ],
    ];

    $this->service->mergeGuestCartToDB($this->customer, $guestCart);

    $item = CustomerCartItem::where('customer_id', $this->customer->id)
        ->where('product_id', $this->productA->id)
        ->first();

    expect($item->quantity)->toBe(7);
});

it('caps merged quantity at 99', function () {
    CustomerCartItem::create([
        'customer_id' => $this->customer->id,
        'product_id' => $this->productA->id,
        'product_variant_id' => 0,
        'quantity' => 90,
    ]);

    $guestCart = [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 20, // 90 + 20 = 110 → capped at 99
        ],
    ];

    $this->service->mergeGuestCartToDB($this->customer, $guestCart);

    $item = CustomerCartItem::where('customer_id', $this->customer->id)
        ->where('product_id', $this->productA->id)
        ->first();

    expect($item->quantity)->toBe(99);
});

it('does nothing when guest cart is empty', function () {
    $this->service->mergeGuestCartToDB($this->customer, []);

    $count = CustomerCartItem::where('customer_id', $this->customer->id)->count();
    expect($count)->toBe(0);
});

it('clears session key after merge so db becomes source of truth', function () {
    Session::put('cart', ['some_key' => ['product_id' => 1, 'product_variant_id' => null, 'quantity' => 1]]);

    $guestCart = [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ],
    ];

    $this->service->mergeGuestCartToDB($this->customer, $guestCart);

    expect(Session::has('cart'))->toBeFalse();
});

it('merges multiple products at once', function () {
    $guestCart = [
        "{$this->productA->id}_0" => [
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'quantity' => 2,
        ],
        "{$this->productB->id}_0" => [
            'product_id' => $this->productB->id,
            'product_variant_id' => null,
            'quantity' => 3,
        ],
    ];

    $this->service->mergeGuestCartToDB($this->customer, $guestCart);

    $itemA = CustomerCartItem::where('customer_id', $this->customer->id)
        ->where('product_id', $this->productA->id)->first();
    $itemB = CustomerCartItem::where('customer_id', $this->customer->id)
        ->where('product_id', $this->productB->id)->first();

    expect($itemA->quantity)->toBe(2)
        ->and($itemB->quantity)->toBe(3);
});
