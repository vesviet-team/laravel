<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerProfileResource\Pages;
use App\Models\SellerProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SellerProfileResource extends Resource
{
    protected static ?string $model = SellerProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Sellers';

    protected static ?string $modelLabel = 'Seller';

    protected static ?string $navigationGroup = 'Shop';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->label('Chủ sở hữu'),
                Forms\Components\TextInput::make('shop_name')
                    ->required()
                    ->maxLength(255)
                    ->label('Tên Shop'),
                Forms\Components\TextInput::make('subdomain')
                    ->required()
                    ->maxLength(255)
                    ->label('Subdomain (cố định)')
                    ->disabled()
                    ->helperText('Subdomain không thể thay đổi sau khi tạo (dùng bởi Spatie tenant resolution).'),
                Forms\Components\TextInput::make('shop_slug')
                    ->label('Shop Slug (/shop/{slug})')
                    ->required()
                    ->regex('/^[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]$|^[a-z0-9]{1,63}$/')
                    ->unique(table: 'seller_profiles', column: 'shop_slug', ignoreRecord: true)
                    ->maxLength(63)
                    ->helperText('Admin only. Chỉ a-z, 0-9, dấu gạch nối (-). URL: demo.tanhdev.com/shop/{slug}'),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255)
                    ->label('Số điện thoại'),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->label('Email'),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Hoạt động',
                        'pending' => 'Chờ duyệt',
                        'suspended' => 'Đình chỉ',
                    ])
                    ->required()
                    ->default('active')
                    ->label('Trạng thái'),
                Forms\Components\TextInput::make('shipping_type')
                    ->default('freeship')
                    ->maxLength(255)
                    ->label('Loại vận chuyển'),
                Forms\Components\TextInput::make('shipping_fee')
                    ->numeric()
                    ->default(0)
                    ->label('Phí vận chuyển'),
                Forms\Components\Textarea::make('bio')
                    ->columnSpanFull()
                    ->label('Giới thiệu'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label('Chủ sở hữu'),
                Tables\Columns\TextColumn::make('shop_name')
                    ->searchable()
                    ->label('Tên Shop'),
                Tables\Columns\TextColumn::make('subdomain')
                    ->searchable()
                    ->label('Subdomain'),
                Tables\Columns\TextColumn::make('shop_slug')
                    ->searchable()
                    ->label('Shop Slug (/shop/...)')
                    ->copyable()
                    ->copyMessage('Đã sao chép!'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label('Số điện thoại'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->label('Trạng thái'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Ngày tạo'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerProfiles::route('/'),
            'create' => Pages\CreateSellerProfile::route('/create'),
            'edit' => Pages\EditSellerProfile::route('/{record}/edit'),
        ];
    }
}
