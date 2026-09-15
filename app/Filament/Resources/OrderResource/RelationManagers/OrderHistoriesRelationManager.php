<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Enums\OrderStatus;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'Lịch sử thay đổi trạng thái';

    protected static ?string $icon = 'heroicon-o-clock';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Not editable
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('new_status')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_status')
                    ->label('Trạng thái cũ')
                    ->badge()
                    ->formatStateUsing(fn (?OrderStatus $state): string => $state ? $state->label() : 'N/A')
                    ->color(fn (?OrderStatus $state): ?string => $state?->color()),
                Tables\Columns\TextColumn::make('new_status')
                    ->label('Trạng thái mới')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Người thực hiện')
                    ->default('Hệ thống'),
                Tables\Columns\TextColumn::make('note')
                    ->label('Ghi chú')
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Read-only
            ])
            ->actions([
                // Read-only
            ])
            ->bulkActions([
                // Read-only
            ])
            ->defaultSort('created_at', 'desc');
    }
}
