<?php

use App\Actions\ProcessLandingOrderAction;
use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('successfully processes landing page order and fires OrderPlaced event', function () {
    Event::fake([OrderPlaced::class]);

    $product = Product::create([
        'name' => 'Landing Product',
        'slug' => 'landing-product-'.uniqid(),
        'price' => 500000,
        'stock' => 20,
        'status' => 'published',
    ]);

    $landingPage = LandingPage::create([
        'title' => 'Special Promo Page',
        'slug' => 'special-promo-'.uniqid(),
        'product_id' => $product->id,
        'is_active' => true,
        'combo_rules_json' => [
            ['id' => 'combo_2', 'name' => 'Combo 2 Sản Phẩm (Giảm 10%)', 'price' => 900000],
        ],
    ]);

    $action = app(ProcessLandingOrderAction::class);

    $order = $action->execute($landingPage, [
        'name' => 'Nguyen Van A',
        'phone' => '0901234567',
        'address' => '123 Le Loi, Quan 1, TP HCM',
        'note' => 'Giao gio hanh chinh',
        'selectedComboId' => 'combo_2',
    ]);

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_amount)->toBe(900000)
        ->and($order->customer_name)->toBe('Nguyen Van A')
        ->and($order->phone)->toBe('0901234567');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'landing_page_id' => $landingPage->id,
        'total_amount' => 900000,
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Combo 2 Sản Phẩm (Giảm 10%)',
        'price_at_purchase' => 900000,
    ]);

    // Verify OrderPlaced event was dispatched
    Event::assertDispatched(OrderPlaced::class, function ($event) use ($order) {
        return $event->order->id === $order->id;
    });
});

it('throws exception if landing page product is out of stock', function () {
    $product = Product::create([
        'name' => 'Out of Stock Product',
        'slug' => 'oos-product-'.uniqid(),
        'price' => 500000,
        'stock' => 0,
        'status' => 'published',
    ]);

    $landingPage = LandingPage::create([
        'title' => 'OOS Page',
        'slug' => 'oos-page-'.uniqid(),
        'product_id' => $product->id,
        'is_active' => true,
    ]);

    $action = app(ProcessLandingOrderAction::class);

    $action->execute($landingPage, [
        'name' => 'Nguyen Van B',
        'phone' => '0901234567',
        'address' => '456 Hai Ba Trung',
        'note' => '',
    ]);
})->throws(RuntimeException::class, 'Sản phẩm hiện tạm hết hàng.');
