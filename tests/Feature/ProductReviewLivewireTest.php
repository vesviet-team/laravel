<?php

use App\Enums\OrderStatus;
use App\Livewire\ProductReviews;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('redirects guest to login when attempting to submit review', function () {
    $product = Product::create([
        'name' => 'Review Product',
        'slug' => 'review-product-'.time(),
        'price' => 100000,
        'stock' => 10,
        'status' => 'published',
    ]);

    Livewire::test(ProductReviews::class, ['product' => $product])
        ->set('rating', 5)
        ->set('comment', 'Awesome!')
        ->call('submitReview')
        ->assertDispatched('show-login-modal');
});

it('rejects review if customer has not purchased and received the product', function () {
    $customer = Customer::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $product = Product::create([
        'name' => 'Review Product',
        'slug' => 'review-product-'.time(),
        'price' => 100000,
        'stock' => 10,
        'status' => 'published',
    ]);

    Livewire::actingAs($customer, 'customer')
        ->test(ProductReviews::class, ['product' => $product])
        ->set('rating', 5)
        ->set('comment', 'Great product!')
        ->call('submitReview')
        ->assertHasErrors(['purchase_required']);

    $this->assertDatabaseMissing('product_reviews', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
    ]);
});

it('allows customer with delivered order to submit review and rejects duplicate review', function () {
    $customer = Customer::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $product = Product::create([
        'name' => 'Review Product',
        'slug' => 'review-product-'.time(),
        'price' => 100000,
        'stock' => 10,
        'status' => 'published',
    ]);

    // Create a delivered order with this product
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-20260819-TEST',
        'status' => OrderStatus::Delivered,
        'payment_method' => 'cod',
        'customer_name' => $customer->name,
        'phone' => '0901234567',
        'address' => '123 Test St',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'shipping_fee' => 0,
        'total_amount' => 100000,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'price_at_purchase' => 100000,
        'subtotal' => 100000,
    ]);

    // First review submission: SUCCESS
    Livewire::actingAs($customer, 'customer')
        ->test(ProductReviews::class, ['product' => $product])
        ->set('rating', 5)
        ->set('comment', 'Awesome product!')
        ->call('submitReview')
        ->assertHasNoErrors()
        ->assertDispatched('review-submitted');

    $this->assertDatabaseHas('product_reviews', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'rating' => 5,
        'comment' => 'Awesome product!',
        'status' => 'pending',
    ]);

    // Second review submission: REJECTED AS DUPLICATE
    Livewire::actingAs($customer, 'customer')
        ->test(ProductReviews::class, ['product' => $product])
        ->set('rating', 4)
        ->set('comment', 'Trying to review again')
        ->call('submitReview')
        ->assertHasErrors(['duplicate_review']);
});
