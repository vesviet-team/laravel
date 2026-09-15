<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopSellingProducts extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->select(
                        DB::raw('MAX(id) as id'),
                        'product_id',
                        'product_name',
                        DB::raw('SUM(quantity) as total_sold'),
                        DB::raw('SUM(quantity * price_at_purchase) as total_revenue')
                    )
                    ->whereHas('order', fn ($q) => $q->whereIn('status', ['completed', 'delivered'])
                    )
                    ->groupBy('product_id', 'product_name')
                    ->orderByDesc('total_sold')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Sản phẩm')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_sold')
                    ->label('Đã bán')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Doanh thu')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.').'₫')
                    ->sortable(),
            ])
            ->heading('Top 10 Sản phẩm bán chạy');
    }
}
