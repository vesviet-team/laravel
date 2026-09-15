<?php

use App\Actions\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('cancels pending order, restores product stock, and fires OrderCancelled event', function () {
    Event::fake([OrderCancelled::class]);

    $product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-prod-'.uniqid(),
        'price' => 200000,
        'stock' => 5, // Initially 5
        'status' => 'published',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-'.uniqid(),
        'status' => OrderStatus::Pending,
        'payment_method' => 'cod',
        'customer_name' => 'Test Customer',
        'phone' => '0901234567',
        'address' => '123 Test St',
        'subtotal' => 400000,
        'discount_amount' => 0,
        'shipping_fee' => 0,
        'total_amount' => 400000,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'price_at_purchase' => 200000,
        'subtotal' => 400000,
    ]);

    // Deduct stock first as if checkout happened
    $product->decrement('stock', 2);
    expect($product->fresh()->stock)->toBe(3);

    $action = app(CancelOrderAction::class);
    $result = $action->execute($order);

    expect($result)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(5); // Stock restored from 3 back to 5

    Event::assertDispatched(OrderCancelled::class, function ($event) use ($order) {
        return $event->order->id === $order->id;
    });
});

it('rejects cancelling an order that is already delivered or shipping', function () {
    $product = Product::create([
        'name' => 'Test Product 2',
        'slug' => 'test-prod-2-'.uniqid(),
        'price' => 100000,
        'stock' => 10,
        'status' => 'published',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-DELIVERED',
        'status' => OrderStatus::Delivered,
        'payment_method' => 'cod',
        'customer_name' => 'Test Customer',
        'phone' => '0901234567',
        'address' => '123 Test St',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'shipping_fee' => 0,
        'total_amount' => 100000,
    ]);

    $action = app(CancelOrderAction::class);
    $action->execute($order);
})->throws(Exception::class, 'Không thể huỷ đơn hàng đang giao hoặc đã giao.');
