<?php

namespace App\Filament\Seller\Resources;

use App\Enums\OrderStatus;
use App\Filament\Seller\Resources\SellerOrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Seller Order Resource — read-heavy, status-update-only.
 *
 * Sellers can:
 *   - View all their orders (scoped by TenantSellerScope via BelongsToSeller)
 *   - Update order status within valid state-machine transitions
 *
 * Sellers CANNOT:
 *   - Delete orders (SellerOrderPolicy::deleteAny returns false)
 *   - Create orders (orders come from storefront)
 *   - Access orders belonging to other sellers
 *
 * Authorization: SellerOrderPolicy (registered in AppServiceProvider)
 * ADR-S3 Trust Zone: Standard
 */
class SellerOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'Đơn hàng';

    protected static ?string $pluralModelLabel = 'Đơn hàng';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin đơn hàng')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Mã đơn hàng')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Tên khách hàng')
                            ->disabled(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Số điện thoại')
                            ->disabled(),
                        Forms\Components\Textarea::make('address')
                            ->label('Địa chỉ')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Trạng thái & Xử lý')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options(function (?Order $record) {
                                if (! $record || ! $record->status instanceof OrderStatus) {
                                    // Fallback (create page, or status not cast yet)
                                    return collect(OrderStatus::cases())
                                        ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])
                                        ->all();
                                }

                                // State machine enforcement (BUG-03 fix):
                                // Only allow valid next transitions + current status in the list.
                                $allowed = $record->status->allowedTransitions();

                                $options = collect($allowed)
                                    ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])
                                    ->all();

                                // Prepend current status so the select shows the current state.
                                return [$record->status->value => '✓ '.$record->status->label().' (hiện tại)'] + $options;
                            })
                            ->required()
                            ->helperText('Chỉ có thể chuyển sang trạng thái hợp lệ theo quy trình.'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Ghi chú nội bộ')
                            ->helperText('Ghi chú này chỉ hiển thị với seller')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Build color mapping from enum for badge display.
        $statusColorFn = fn (string $state): string => OrderStatus::tryFrom($state)?->color() ?? 'gray';

        $statusLabelFn = fn (string $state): string => OrderStatus::tryFrom($state)?->label() ?? $state;

        // Build filter options from enum — DRY, consistent with form.
        $statusFilterOptions = collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])
            ->all();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Mã đơn hàng')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Khách hàng')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Số điện thoại'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color($statusColorFn)
                    ->formatStateUsing($statusLabelFn),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày đặt')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options($statusFilterOptions),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            // DeleteBulkAction intentionally removed:
            // - SellerOrderPolicy::deleteAny() returns false
            // - Orders are financial records and must not be destroyed by sellers
            // See SF-01 in implementation plan.
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerOrders::route('/'),
            'view' => Pages\ViewSellerOrder::route('/{record}'),
            'edit' => Pages\EditSellerOrder::route('/{record}/edit'),
        ];
    }
}
