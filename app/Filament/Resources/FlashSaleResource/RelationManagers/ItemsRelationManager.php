<?php

namespace App\Filament\Resources\FlashSaleResource\RelationManagers;

use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->rules([
                        fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $product = Product::find($get('product_id'));
                            if ($product && $value >= $product->price) {
                                $fail('Giá Flash Sale ('.number_format($value, 0, ',', '.').'đ) phải nhỏ hơn giá gốc của sản phẩm ('.number_format($product->price, 0, ',', '.').'đ).');
                            }
                        },
                    ]),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('sold_quantity')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                Tables\Columns\TextColumn::make('product.name'),
                Tables\Columns\TextColumn::make('price')->money('VND'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('sold_quantity'),
            ])
            ->filters([
                //
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
