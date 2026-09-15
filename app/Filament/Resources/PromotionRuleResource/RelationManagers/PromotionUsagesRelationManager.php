<?php

namespace App\Filament\Resources\PromotionRuleResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\PromotionUsage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionUsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Lịch Sử Sử Dụng Khuyến Mãi (Usage Audit Log)';

    protected static ?string $modelLabel = 'Lượt sử dụng';

    protected static ?string $pluralModelLabel = 'Lịch sử sử dụng';

    protected static ?string $recordTitleAttribute = 'email';

    /**
     * Mark this relation manager as strictly read-only.
     * Prevents manual creation, modification, or record deletion.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->label('Email áp dụng')
                    ->disabled(),
                Forms\Components\TextInput::make('discount_amount')
                    ->label('Số tiền chiết khấu (₫)')
                    ->numeric()
                    ->disabled(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Thời gian ghi nhận')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Mã Đơn Hàng')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—')
                    ->url(fn (?PromotionUsage $record): ?string => $record?->order_id && class_exists(OrderResource::class)
                        ? OrderResource::getUrl('edit', ['record' => $record->order_id])
                        : null)
                    ->openUrlInNewTab()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('customer.name')
                    ->label('Khách Hàng')
                    ->searchable()
                    ->sortable()
                    ->default(fn (PromotionUsage $record): string => $record->customer_name ?? 'Khách vãng lai')
                    ->description(fn (PromotionUsage $record): ?string => $record->customer?->phone ? 'SĐT: '.$record->customer->phone : null),

                TextColumn::make('email')
                    ->label('Email Sử Dụng')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Đã sao chép địa chỉ email!')
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('discount_amount')
                    ->label('Số Tiền Đã Giảm')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.').'₫')
                    ->sortable()
                    ->color('success')
                    ->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.').'₫')
                            ->label('Tổng giá trị đã giảm'),
                    ]),

                TextColumn::make('created_at')
                    ->label('Thời Điểm Sử Dụng')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('used_from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('used_until')->label('Đến ngày'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['used_from'],
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['used_until'],
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    }),

                SelectFilter::make('customer_type')
                    ->label('Loại khách hàng')
                    ->options([
                        'registered' => 'Thành viên đã đăng ký',
                        'guest' => 'Khách vãng lai (Guest)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'registered') {
                            return $query->whereNotNull('customer_id');
                        }
                        if ($data['value'] === 'guest') {
                            return $query->whereNull('customer_id');
                        }

                        return $query;
                    }),
            ])
            ->headerActions([
                // Intentionally empty: No create action on immutable audit log
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Chi tiết giao dịch áp dụng khuyến mãi')
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('order.order_number')
                                ->label('Mã đơn hàng'),
                            Forms\Components\TextInput::make('email')
                                ->label('Email người dùng'),
                            Forms\Components\TextInput::make('customer.name')
                                ->label('Tên khách hàng')
                                ->default(fn (PromotionUsage $record): string => $record->customer_name ?? 'Khách vãng lai'),
                            Forms\Components\TextInput::make('discount_amount')
                                ->label('Số tiền giảm')
                                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.').'₫'),
                            Forms\Components\DateTimePicker::make('created_at')
                                ->label('Thời gian ghi nhận'),
                        ]),
                    ]),
            ])
            ->bulkActions([
                // Intentionally empty: No bulk deletion on immutable audit log
            ]);
    }
}
