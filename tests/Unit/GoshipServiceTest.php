<?php

use App\Models\Order;
use App\Services\GoshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('fetches shipping rates from Goship API', function () {
    Http::fake([
        'goship.io/api/v2/rates' => Http::response([
            'data' => [
                ['carrier' => 'GHN', 'fee' => 30000],
                ['carrier' => 'GHTK', 'fee' => 35000],
                ['carrier' => 'ViettelPost', 'fee' => 32000],
            ],
        ], 200),
    ]);

    $service = new GoshipService;
    $rates = $service->getShippingRates([
        'city' => 'Hanoi',
        'district' => 'Ba Dinh',
        'ward' => 'Ngoc Ha',
    ], ['weight' => 500]);

    expect($rates)->toHaveCount(3);
    expect($rates[0]['fee'])->toBe(30000);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'goship.io/api/v2/rates');
    });
});

it('creates a waybill via Goship API', function () {
    Http::fake([
        'goship.io/api/v2/shipments' => Http::response([
            'data' => [
                'id' => 'WAYBILL123',
                'status' => 'created',
            ],
        ], 200),
    ]);

    $order = Order::create([
        'order_number' => 'ORD-'.time(),
        'customer_name' => 'John',
        'phone' => '0901234567',
        'address' => '123 Main St',
        'subtotal' => 100,
        'total_amount' => 100,
        'status' => 'pending',
        'payment_method' => 'cod',
    ]);

    $service = new GoshipService;
    $waybill = $service->createWaybill($order);

    expect($waybill['id'])->toBe('WAYBILL123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'goship.io/api/v2/shipments');
    });
});

it('blocks waybill creation for unpaid non-COD order', function () {
    $order = Order::create([
        'order_number' => 'ORD-UNPAID-'.time(),
        'customer_name' => 'Jane',
        'phone' => '0907654321',
        'address' => '456 Side St',
        'subtotal' => 200,
        'total_amount' => 200,
        'status' => 'pending',
        'payment_method' => 'vietqr',
        'payment_status' => 'pending',
    ]);

    $service = new GoshipService;
    $waybill = $service->createWaybill($order);

    expect($waybill)->toBeNull();
});
