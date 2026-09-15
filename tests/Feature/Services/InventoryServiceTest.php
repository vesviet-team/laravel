<?php

namespace Tests\Feature\Services;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Exception;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->inventoryService = new InventoryService;
});

it('deducts stock for an order', function () {
    $product = Product::create(['name' => 'P1', 'slug' => Str::random(10), 'price' => 10, 'stock' => 10]);
    $variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'V1', 'price' => 15, 'stock' => 5]);

    $order = Order::create(['customer_name' => 'Test', 'phone' => '123', 'address' => 'Addr', 'order_number' => Str::random(10), 'payment_method' => 'cod', 'subtotal' => 25, 'total_amount' => 25]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_variant_id' => null,
        'quantity' => 2,
        'price_at_purchase' => 10,
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'price_at_purchase' => 15,
    ]);

    $this->inventoryService->deductStock($order);

    expect($product->fresh()->stock)->toBe(8)
        ->and($variant->fresh()->stock)->toBe(4);
});

it('restores stock for an order', function () {
    $product = Product::create(['name' => 'P2', 'slug' => Str::random(10), 'price' => 10, 'stock' => 8]);
    $variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'V1', 'price' => 15, 'stock' => 4]);

    $order = Order::create(['customer_name' => 'Test', 'phone' => '123', 'address' => 'Addr', 'order_number' => Str::random(10), 'payment_method' => 'cod', 'subtotal' => 25, 'total_amount' => 25]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_variant_id' => null,
        'quantity' => 2,
        'price_at_purchase' => 10,
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'price_at_purchase' => 15,
    ]);

    $this->inventoryService->restoreStock($order);

    expect($product->fresh()->stock)->toBe(10)
        ->and($variant->fresh()->stock)->toBe(5);
});

it('throws exception when stock is insufficient', function () {
    $product = Product::create(['name' => 'P3', 'slug' => Str::random(10), 'price' => 10, 'stock' => 1]);

    $order = Order::create(['customer_name' => 'Test', 'phone' => '123', 'address' => 'Addr', 'order_number' => Str::random(10), 'payment_method' => 'cod', 'subtotal' => 20, 'total_amount' => 20]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_variant_id' => null,
        'quantity' => 2,
        'price_at_purchase' => 10,
    ]);

    $this->inventoryService->deductStock($order);
})->throws(Exception::class);

it('deducts and restores flash sale stock correctly', function () {
    $product = Product::create(['name' => 'Flash P', 'slug' => Str::random(10), 'price' => 10, 'stock' => 10]);
    $flashSale = FlashSale::create(['name' => 'FS', 'status' => 'active', 'start_time' => now()->subDay(), 'end_time' => now()->addDay()]);
    $flashSaleItem = FlashSaleItem::create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'price' => 5, 'quantity' => 5, 'sold_quantity' => 0]);

    $order = Order::create(['customer_name' => 'Test', 'phone' => '123', 'address' => 'Addr', 'order_number' => Str::random(10), 'payment_method' => 'cod', 'subtotal' => 10, 'total_amount' => 10]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_variant_id' => null,
        'quantity' => 2,
        'price_at_purchase' => 5,
        'is_flash_sale' => true,
    ]);

    $this->inventoryService->deductStock($order);

    expect($product->fresh()->stock)->toBe(8)
        ->and($flashSaleItem->fresh()->sold_quantity)->toBe(2);

    $this->inventoryService->restoreStock($order);

    expect($product->fresh()->stock)->toBe(10)
        ->and($flashSaleItem->fresh()->sold_quantity)->toBe(0);
});
