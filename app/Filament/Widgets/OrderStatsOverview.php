<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Admin dashboard stats widget.
 *
 * Revenue stats count only 'delivered' orders (terminal success state).
 * 'confirmed', 'processing', 'shipped' represent expected revenue, tracked separately.
 *
 * Bug fixed 2026-09-01: prior version queried 'completed' (no such enum case)
 * and 'shipping' (enum is 'shipped') — both always returned 0.
 */
class OrderStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        // Revenue: only count terminal delivered orders (actual confirmed revenue).
        $todayRevenue = Order::where('status', OrderStatus::Delivered->value)
            ->whereDate('created_at', today())
            ->sum('total_amount');

        $monthRevenue = Order::where('status', OrderStatus::Delivered->value)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        // In-flight revenue: orders confirmed but not yet delivered.
        $inflightRevenue = Order::whereIn('status', [
            OrderStatus::Confirmed->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
        ])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        $pendingCount = Order::where('status', OrderStatus::Pending->value)->count();

        $shippingCount = Order::where('status', OrderStatus::Shipped->value)->count();

        return [
            Stat::make('Doanh thu hôm nay', number_format($todayRevenue, 0, ',', '.').'₫')
                ->description('Đơn đã giao thành công')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Doanh thu tháng '.now()->month, number_format($monthRevenue, 0, ',', '.').'₫')
                ->description('Thực thu tháng '.now()->format('m/Y'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Đơn chờ xử lý', $pendingCount)
                ->description('Cần xác nhận ngay')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Đang vận chuyển', $shippingCount)
                ->description('Đơn đang trên đường giao')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),
        ];
    }
}
