<?php

use App\Actions\UpdateOrderStatusAction;
use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('updates order status according to state machine and logs history', function () {
    Event::fake([OrderStatusUpdated::class]);

    $user = User::factory()->create();
    $seller = SellerProfile::create([
        'user_id' => $user->id,
        'shop_name' => 'Test Seller',
        'subdomain' => 'test-seller',
        'shop_slug' => 'test-seller',
        'status' => 'active',
    ]);

    $order = Order::create([
        'seller_id' => $seller->id,
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

    $action = app(UpdateOrderStatusAction::class);

    // Test: Pending -> Confirmed
    $result = $action->execute($order, OrderStatus::Confirmed);

    expect($result)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);

    $history = $order->histories()->latest()->first();
    expect($history)->not->toBeNull()
        ->and($history->old_status)->toBe(OrderStatus::Pending)
        ->and($history->new_status)->toBe(OrderStatus::Confirmed)
        ->and($history->seller_id)->toBe($seller->id);

    Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
        return $event->order->id === $order->id && $event->newStatus === OrderStatus::Confirmed;
    });

    // Test: Confirmed -> Processing
    $action->execute($order, OrderStatus::Processing);
    expect($order->fresh()->status)->toBe(OrderStatus::Processing);

    // Test: Processing -> Shipped with note
    $waybill = 'WB-123456';
    $action->execute($order, OrderStatus::Shipped, $waybill);

    $historyShipped = $order->histories()->latest()->first();
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and($historyShipped->new_status)->toBe(OrderStatus::Shipped)
        ->and($historyShipped->note)->toBe($waybill);
});

it('rejects invalid state machine transitions', function () {
    $user = User::factory()->create();
    $seller = SellerProfile::create([
        'user_id' => $user->id,
        'shop_name' => 'Test Seller',
        'subdomain' => 'test-seller',
        'shop_slug' => 'test-seller',
        'status' => 'active',
    ]);

    $order = Order::create([
        'seller_id' => $seller->id,
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

    $action = app(UpdateOrderStatusAction::class);

    // Cannot jump from Pending directly to Delivered
    $action->execute($order, OrderStatus::Delivered);
})->throws(Exception::class, "Không thể chuyển trạng thái từ 'Chờ xác nhận' sang 'Đã giao hàng'.");

it('handles cancellation and restores stock successfully with logging', function () {
    $product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-prod-'.uniqid(),
        'price' => 200000,
        'stock' => 5,
        'status' => 'published',
    ]);

    $user = User::factory()->create();
    $seller = SellerProfile::create([
        'user_id' => $user->id,
        'shop_name' => 'Test Seller',
        'subdomain' => 'test-seller',
        'shop_slug' => 'test-seller',
        'status' => 'active',
    ]);

    $order = Order::create([
        'seller_id' => $seller->id,
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

    $product->decrement('stock', 2);
    expect($product->fresh()->stock)->toBe(3);

    $action = app(UpdateOrderStatusAction::class);
    $cancelReason = 'Khách hàng đổi ý';
    $result = $action->execute($order, OrderStatus::Cancelled, $cancelReason);

    expect($result)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(5); // Stock restored!

    $history = $order->histories()->latest()->first();
    expect($history->new_status)->toBe(OrderStatus::Cancelled)
        ->and($history->note)->toBe($cancelReason);
});
