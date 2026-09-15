<?php

use App\Actions\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Livewire\CheckoutFlow;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Decor',
        'slug' => 'decor',
    ]);

    $this->product = Product::create([
        'name' => 'Ceramic Flower Vase',
        'slug' => 'ceramic-flower-vase',
        'sku' => 'VASE-001',
        'price' => 500000,
        'stock' => 10,
        'category_id' => $this->category->id,
        'status' => 'published',
    ]);
});

it('provides real-time cart summary and stock validation', function () {
    $cartService = app(CartService::class);
    $cartService->clear();

    $cartService->add($this->product->id, null, 2);

    $summary = $cartService->getSummary();
    expect($summary['is_empty'])->toBeFalse();
    expect($summary['item_count'])->toBe(2);
    expect($summary['subtotal'])->toEqual(1000000);

    $stockIssues = $cartService->validateStock();
    expect($stockIssues)->toBeEmpty();

    // Now request 15 items when only 10 available
    $cartService->update($this->product->id, null, 15);
    $issues = $cartService->validateStock();
    expect($issues)->toHaveCount(1);
    expect($issues[0]['requested'])->toBe(15);
    expect($issues[0]['available'])->toBe(10);
});

it('executes full checkout process and creates order with deducted inventory and combo discount', function () {
    Event::fake([OrderPlaced::class]);
    $cartService = app(CartService::class);
    $cartService->clear();
    $cartService->add($this->product->id, null, 2);

    $shippingData = [
        'customer_name' => 'Nguyen Van A',
        'phone' => '0912345678',
        'email' => 'nguyenvana@example.com',
        'address' => '456 Nguyen Hue',
        'city' => 'Ho Chi Minh',
        'district' => 'District 1',
        'ward' => 'Ben Nghe',
    ];

    $component = Livewire::test(CheckoutFlow::class)
        ->set('shippingData', $shippingData)
        ->set('selectedPaymentMethod', 'cod')
        ->call('submitOrder');

    $order = Order::where('email', 'nguyenvana@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->subtotal)->toBe(1000000);
    expect($order->discount_amount)->toBe(50000); // 5% combo discount for 2+ items
    expect($order->total_amount)->toBe(950000);
    expect($order->items)->toHaveCount(1);

    // Verify inventory was deducted from 10 to 8
    $this->product->refresh();
    expect($this->product->stock)->toBe(8);

    $component->assertRedirect(route('checkout.success', ['order_number' => $order->order_number]));
});

it('tracks order by order number and phone or email', function () {
    $order = Order::create([
        'order_number' => 'ORD-2026-TEST01',
        'customer_name' => 'Le Thi B',
        'phone' => '0987654321',
        'email' => 'lethib@example.com',
        'address' => '789 Tran Hung Dao',
        'status' => OrderStatus::Processing,
        'payment_method' => 'cod',
        'subtotal' => 500000,
        'total_amount' => 500000,
        'discount_amount' => 0,
        'shipping_fee' => 0,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'sku' => $this->product->sku,
        'price_at_purchase' => 500000,
        'quantity' => 1,
    ]);

    // Tracking with matching phone
    $response = $this->get(route('track-order.index', [
        'order_number' => 'ORD-2026-TEST01',
        'contact_info' => '0987654321',
    ]));

    $response->assertStatus(200);
    $response->assertSee('ORD-2026-TEST01');
    $response->assertSee('Ceramic Flower Vase');
    $response->assertSee('500.000₫');

    // Tracking with wrong phone returns friendly error
    $responseWrong = $this->get(route('track-order.index', [
        'order_number' => 'ORD-2026-TEST01',
        'contact_info' => '0000000000',
    ]));

    $responseWrong->assertStatus(200);
    $responseWrong->assertSee('Không tìm thấy đơn hàng với mã');
});

it('cancels an order and automatically restores stock', function () {
    // Initial stock: 10
    // Create order with 3 items deducted
    $this->product->decrement('stock', 3);
    expect($this->product->fresh()->stock)->toBe(7);

    $order = Order::create([
        'order_number' => 'ORD-CANCEL-001',
        'customer_name' => 'Tran Van C',
        'phone' => '0933112233',
        'address' => '12 Hai Ba Trung',
        'status' => OrderStatus::Pending,
        'payment_method' => 'cod',
        'subtotal' => 1500000,
        'total_amount' => 1500000,
        'discount_amount' => 0,
        'shipping_fee' => 0,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'sku' => $this->product->sku,
        'price_at_purchase' => 500000,
        'quantity' => 3,
    ]);

    $cancelAction = app(CancelOrderAction::class);
    $result = $cancelAction->execute($order);

    expect($result)->toBeTrue();
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    // Stock must be restored from 7 back to 10
    expect($this->product->fresh()->stock)->toBe(10);
});
