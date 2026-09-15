<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Living Room',
        'slug' => 'living-room',
    ]);

    $this->product = Product::create([
        'name' => 'Oak Table',
        'slug' => 'oak-table',
        'sku' => 'TBL-001',
        'price' => 1000000,
        'stock' => 10,
        'weight' => 2500, // 2.5 kg
        'category_id' => $this->category->id,
        'status' => 'published',
    ]);
});

test('CartService includes real product weight in enriched cart items', function () {
    $cartService = app(CartService::class);
    $cartService->add($this->product->id, null, 2);

    $items = $cartService->getCartItemsDetails();

    expect($items)->toHaveCount(1)
        ->and($items[0]['weight'])->toBe(2500)
        ->and($items[0]['quantity'])->toBe(2);
});

test('CartService includes variant weight when configured', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Large Oak Table',
        'sku' => 'TBL-001-L',
        'price' => 1500000,
        'stock' => 5,
        'weight' => 4000, // 4 kg override
        'is_active' => true,
    ]);

    $cartService = app(CartService::class);
    $cartService->add($this->product->id, $variant->id, 1);

    $items = $cartService->getCartItemsDetails();

    expect($items)->toHaveCount(1)
        ->and($items[0]['weight'])->toBe(4000);
});

test('Checkout creates order with payment_status unpaid and expiry for online payment', function () {
    $cartService = app(CartService::class);
    $cartService->add($this->product->id, null, 1);

    $response = $this->post('/checkout', [
        'shippingData' => [
            'customer_name' => 'Le Van B',
            'phone' => '0912345678',
            'email' => 'levanb@example.com',
            'address' => '456 Le Loi',
            'city' => 'Ho Chi Minh',
            'district' => 'District 1',
            'ward' => 'Ben Nghe',
        ],
        'payment_method' => 'banking',
    ]);

    $response->assertRedirect();

    $order = Order::where('email', 'levanb@example.com')->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_status)->toBe('unpaid')
        ->and($order->payment_method)->toBe('banking')
        ->and($order->payment_expires_at)->not->toBeNull()
        ->and($order->payment_details)->toHaveKey('qr_url');
});

test('Checkout creates COD order without payment expiration', function () {
    $cartService = app(CartService::class);
    $cartService->add($this->product->id, null, 1);

    $response = $this->post('/checkout', [
        'shippingData' => [
            'customer_name' => 'Nguyen Van COD',
            'phone' => '0987654321',
            'address' => '789 Tran Phu',
            'city' => 'Ha Noi',
            'district' => 'Ba Dinh',
            'ward' => 'Lieu Giai',
        ],
        'payment_method' => 'cod',
    ]);

    $response->assertRedirect();

    $order = Order::where('customer_name', 'Nguyen Van COD')->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_status)->toBe('unpaid')
        ->and($order->payment_method)->toBe('cod')
        ->and($order->payment_expires_at)->toBeNull();
});

test('Command orders:cancel-expired-unpaid auto-cancels expired orders and restores stock', function () {
    // 1. Create an expired unpaid order with 2 reserved items
    $order = Order::create([
        'order_number' => 'ORD-EXP-001',
        'status' => OrderStatus::Pending,
        'payment_method' => 'banking',
        'payment_status' => 'unpaid',
        'payment_expires_at' => now()->subMinute(), // Expired 1 minute ago
        'customer_name' => 'Expired User',
        'phone' => '0901234567',
        'address' => '123 Expired St',
        'subtotal' => 2000000,
        'total_amount' => 2000000,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'price_at_purchase' => 1000000,
        'quantity' => 2,
    ]);

    // Initial stock is 10. Simulate that 2 units were deducted
    $this->product->update(['stock' => 8]);

    // 2. Run the cleanup command
    Artisan::call('orders:cancel-expired-unpaid');

    // 3. Verify order status and restored inventory
    $order->refresh();
    $this->product->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->payment_status)->toBe('expired')
        ->and($this->product->stock)->toBe(10); // 8 + 2 restored
});

test('Command orders:cancel-expired-unpaid leaves COD orders unaffected', function () {
    $codOrder = Order::create([
        'order_number' => 'ORD-COD-001',
        'status' => OrderStatus::Pending,
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'payment_expires_at' => now()->subHour(),
        'customer_name' => 'COD User',
        'phone' => '0901234567',
        'address' => '123 COD St',
        'subtotal' => 1000000,
        'total_amount' => 1000000,
    ]);

    Artisan::call('orders:cancel-expired-unpaid');

    $codOrder->refresh();
    expect($codOrder->status)->toBe(OrderStatus::Pending)
        ->and($codOrder->payment_status)->toBe('unpaid');
});
