<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Doanh thu';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, string> */
    protected $filters = [
        'today' => 'Hôm nay',
        '7days' => '7 ngày',
        '30days' => '30 ngày',
        '3months' => '3 tháng',
    ];

    public ?string $filter = '30days';

    protected function getData(): array
    {
        [$startDate, $groupFormat, $labelFormat] = match ($this->filter) {
            'today' => [now()->startOfDay(), 'H:00', 'H:i'],
            '7days' => [now()->subDays(6)->startOfDay(), 'Y-m-d', 'd/m'],
            '3months' => [now()->subMonths(3)->startOfDay(), 'Y-m', 'm/Y'],
            default => [now()->subDays(29)->startOfDay(), 'Y-m-d', 'd/m'],
        };

        $orders = Order::whereIn('status', ['completed', 'delivered'])
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($order) => Carbon::parse($order->created_at)->format($groupFormat));

        $labels = [];
        $data = [];

        foreach ($orders as $period => $group) {
            // Re-format label for display
            try {
                $dt = Carbon::createFromFormat($groupFormat, $period);
                $labels[] = $dt->format($labelFormat);
            } catch (\Exception) {
                $labels[] = $period;
            }

            $data[] = (float) $group->sum('total_amount');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Doanh thu (₫)',
                    'data' => $data,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
