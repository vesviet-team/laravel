<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    protected static ?string $title = 'Giới Thiệu';

    protected static ?string $modelLabel = 'người được giới thiệu';

    protected static ?string $pluralModelLabel = 'người được giới thiệu';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Tên')
                ->disabled(),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->disabled(),

            Forms\Components\TextInput::make('phone')
                ->label('Điện thoại')
                ->disabled(),

            Forms\Components\TextInput::make('referral_code')
                ->label('Mã giới thiệu của họ')
                ->disabled(),

            Forms\Components\TextInput::make('loyalty_points')
                ->label('Điểm thưởng')
                ->disabled()
                ->numeric(),

            Forms\Components\DateTimePicker::make('created_at')
                ->label('Ngày tham gia')
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(),

                Tables\Columns\TextColumn::make('referral_code')
                    ->label('Mã giới thiệu')
                    ->copyable()
                    ->copyMessage('Đã sao chép mã')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('loyalty_points')
                    ->label('Điểm thưởng')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => number_format((int) $state).'đ')
                    ->sortable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Số đơn hàng')
                    ->numeric()
                    ->sortable()
                    ->default(0),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tham gia')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('has_orders')
                    ->label('Đã đặt hàng')
                    ->query(fn ($query) => $query->whereHas('orders', fn ($q) => $q->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
