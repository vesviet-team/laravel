<?php

namespace App\Filament\Resources;

use App\Actions\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Order Information')->schema([
                        Forms\Components\TextInput::make('order_number')->disabled(),
                        Forms\Components\Placeholder::make('status_label')
                            ->label('Status')
                            ->content(fn (?Order $record) => $record?->status instanceof OrderStatus ? $record->status->label() : ($record?->status ? OrderStatus::tryFrom($record->status)?->label() : 'N/A')),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'cod' => 'Cash on Delivery',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('utm_source')->disabled(),
                    ])->columns(2),

                    Forms\Components\Section::make('Order Items')->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\TextInput::make('product_name')->disabled(),
                                Forms\Components\TextInput::make('variant_name')->disabled(),
                                Forms\Components\TextInput::make('sku')->disabled(),
                                Forms\Components\TextInput::make('price_at_purchase')->numeric()->disabled(),
                                Forms\Components\TextInput::make('quantity')->numeric()->disabled(),
                            ])
                            ->columns(5)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),
                    ]),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Customer Details')->schema([
                        Forms\Components\TextInput::make('customer_name')->disabled(),
                        Forms\Components\TextInput::make('phone')->disabled(),
                        Forms\Components\TextInput::make('email')->email()->disabled(),
                        Forms\Components\TextInput::make('address')->disabled(),
                        Forms\Components\TextInput::make('city')->disabled(),
                        Forms\Components\TextInput::make('district')->disabled(),
                        Forms\Components\TextInput::make('ward')->disabled(),
                        Forms\Components\Textarea::make('notes')->disabled(),
                    ]),

                    Forms\Components\Section::make('Financials')->schema([
                        Forms\Components\TextInput::make('subtotal')->numeric()->disabled()->suffix('₫'),
                        Forms\Components\TextInput::make('discount_amount')->numeric()->disabled()->suffix('₫'),
                        Forms\Components\TextInput::make('shipping_fee')->numeric()->disabled()->suffix('₫'),
                        Forms\Components\TextInput::make('total_amount')->numeric()->disabled()->suffix('₫'),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->searchable(),
                Tables\Columns\TextColumn::make('customer_name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', '.').'₫')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OrderStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel_order')
                    ->label('Huỷ đơn & hoàn kho')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận huỷ đơn hàng')
                    ->modalDescription('Bạn có chắc chắn muốn huỷ đơn hàng này? Hệ thống sẽ tự động hoàn trả số lượng tồn kho sản phẩm vào kho hàng.')
                    ->modalSubmitActionLabel('Xác nhận huỷ')
                    ->visible(fn (Order $record): bool => ! ($record->status instanceof OrderStatus ? $record->status : OrderStatus::tryFrom($record->status))?->isTerminal())
                    ->action(function (Order $record) {
                        try {
                            app(CancelOrderAction::class)->execute($record);
                            Notification::make()
                                ->title('Huỷ đơn hàng thành công')
                                ->body('Đơn hàng đã được huỷ và tồn kho đã được hoàn lại.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Không thể huỷ đơn hàng')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Direct bulk deleting is disabled to preserve financial data integrity
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            OrderResource\RelationManagers\OrderHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
