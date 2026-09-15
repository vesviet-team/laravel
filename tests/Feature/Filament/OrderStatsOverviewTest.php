<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Filament\Widgets\OrderStatsOverview;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;

uses(RefreshDatabase::class);

/**
 * Helper: invoke protected getStats() via ReflectionMethod.
 * getStats() is protected in StatsOverviewWidget — Livewire __call() intercepts
 * public method calls before PHP resolves visibility, causing BadMethodCallException.
 */
function callGetStats(OrderStatsOverview $widget): array
{
    $ref = new ReflectionMethod($widget, 'getStats');
    $ref->setAccessible(true);

    return $ref->invoke($widget);
}

function makeOrder(string $orderNumber, OrderStatus $status, int $amount): Order
{
    return Order::create([
        'order_number' => $orderNumber,
        'status' => $status->value,   // use ->value for MySQL ENUM safety
        'total_amount' => $amount,
        'subtotal' => $amount,
        'discount_amount' => 0,
        'shipping_fee' => 0,
        'customer_name' => 'Test',
        'phone' => '0900000000',
        'address' => 'Test Addr',
        'payment_method' => 'cod',
        'created_at' => now(),
    ]);
}

it('counts only delivered orders in today revenue', function () {
    makeOrder('ORD-D-001', OrderStatus::Delivered, 1_000_000);
    makeOrder('ORD-S-001', OrderStatus::Shipped, 500_000);  // NOT delivered
    makeOrder('ORD-P-001', OrderStatus::Pending, 200_000);  // NOT delivered

    $widget = new OrderStatsOverview;
    $stats = callGetStats($widget);

    expect($stats[0]->getValue())->toBe('1.000.000₫');
});

it('counts pending orders correctly', function () {
    makeOrder('ORD-P-002', OrderStatus::Pending, 100_000);
    makeOrder('ORD-P-003', OrderStatus::Pending, 100_000);
    makeOrder('ORD-C-001', OrderStatus::Confirmed, 100_000);

    $widget = new OrderStatsOverview;
    $stats = callGetStats($widget);

    expect((int) $stats[2]->getValue())->toBe(2);
});

it('counts shipped orders using correct enum value not stale shipping string', function () {
    makeOrder('ORD-SH-001', OrderStatus::Shipped, 300_000);

    $widget = new OrderStatsOverview;
    $stats = callGetStats($widget);

    // Regression guard: was always 0 because widget queried status = 'shipping'
    // but OrderStatus::Shipped->value = 'shipped'
    expect((int) $stats[3]->getValue())->toBe(1);
});

it('does not count cancelled orders as delivered revenue', function () {
    // Use a valid ENUM value — 'completed' is NOT a valid orders.status value.
    // MySQL strict mode throws SQLSTATE[01000] Warning 1265 for invalid ENUM values.
    makeOrder('ORD-CANCEL-001', OrderStatus::Cancelled, 9_999_999);

    $widget = new OrderStatsOverview;
    $stats = callGetStats($widget);

    // Cancelled orders must NOT appear in today revenue
    expect($stats[0]->getValue())->toBe('0₫');
});
