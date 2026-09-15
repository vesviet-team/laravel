<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Địa Chỉ';

    protected static ?string $modelLabel = 'địa chỉ';

    protected static ?string $pluralModelLabel = 'địa chỉ';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->options([
                    'shipping' => 'Giao Hàng',
                    'billing' => 'Thanh Toán',
                ])
                ->required()
                ->default('shipping'),

            Forms\Components\TextInput::make('label')
                ->label('Nhãn')
                ->placeholder('Ví dụ: Nhà riêng, Văn phòng')
                ->maxLength(50),

            Forms\Components\TextInput::make('recipient_name')
                ->label('Tên người nhận')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('phone')
                ->label('Số điện thoại')
                ->tel()
                ->required()
                ->maxLength(20)
                ->rule("regex:/^(\+84|0)[0-9]{9,10}$/"),

            Forms\Components\Textarea::make('address_line_1')
                ->label('Địa chỉ chi tiết')
                ->required()
                ->maxLength(500)
                ->placeholder('Số nhà, tên đường, tòa nhà...'),

            Forms\Components\Textarea::make('address_line_2')
                ->label('Địa chỉ bổ sung')
                ->maxLength(500)
                ->placeholder('Khu dân cư, khu phố, thôn, xóm...'),

            Forms\Components\TextInput::make('city')
                ->label('Tỉnh/Thành phố')
                ->required()
                ->maxLength(100),

            Forms\Components\TextInput::make('district')
                ->label('Quận/Huyện')
                ->required()
                ->maxLength(100),

            Forms\Components\TextInput::make('ward')
                ->label('Phường/Xã')
                ->maxLength(100),

            Forms\Components\TextInput::make('postal_code')
                ->label('Mã bưu chính')
                ->maxLength(20),

            Forms\Components\TextInput::make('country')
                ->label('Quốc gia')
                ->default('Vietnam')
                ->maxLength(100),

            Forms\Components\Toggle::make('is_default')
                ->label('Địa chỉ mặc định')
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_name')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'shipping' => 'info',
                        'billing' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'shipping' => 'Giao Hàng',
                        'billing' => 'Thanh Toán',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('label')
                    ->label('Nhãn')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('recipient_name')
                    ->label('Người nhận')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(),

                Tables\Columns\TextColumn::make('formatted_address')
                    ->label('Địa chỉ đầy đủ')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->formatted_address),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Mặc định')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'shipping' => 'Giao Hàng',
                        'billing' => 'Thanh Toán',
                    ])
                    ->label('Loại địa chỉ'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
