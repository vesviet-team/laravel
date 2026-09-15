<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Nhật Ký Hoạt Động';

    protected static ?string $modelLabel = 'nhật ký';

    protected static ?string $pluralModelLabel = 'nhật ký hoạt động';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('action')
                ->label('Hành động')
                ->disabled(),

            Forms\Components\TextInput::make('description')
                ->label('Mô tả')
                ->disabled(),

            Forms\Components\KeyValue::make('old_values')
                ->label('Giá trị cũ')
                ->disabled()
                ->keyLabel('Trường')
                ->valueLabel('Giá trị'),

            Forms\Components\KeyValue::make('new_values')
                ->label('Giá trị mới')
                ->disabled()
                ->keyLabel('Trường')
                ->valueLabel('Giá trị'),

            Forms\Components\TextInput::make('ip_address')
                ->label('Địa chỉ IP')
                ->disabled(),

            Forms\Components\TextInput::make('user_agent')
                ->label('User Agent')
                ->disabled(),

            Forms\Components\DateTimePicker::make('created_at')
                ->label('Thời gian')
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->label('Hành động')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login', 'logout' => 'info',
                        'password_change', 'two_factor_enable', 'two_factor_disable' => 'warning',
                        'profile_update', 'address_create', 'address_update', 'address_delete' => 'success',
                        'data_export', 'account_deletion' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Str::ucfirst(str_replace('_', ' ', $state)))
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Mô tả')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->copyMessage('Đã sao chép IP')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'login' => 'Đăng Nhập',
                        'logout' => 'Đăng Xuất',
                        'password_change' => 'Đổi Mật Khẩu',
                        'two_factor_enable' => 'Bật 2FA',
                        'two_factor_disable' => 'Tắt 2FA',
                        'profile_update' => 'Cập Nhật Hồ Sơ',
                        'address_create' => 'Thêm Địa Chỉ',
                        'address_update' => 'Cập Nhật Địa Chỉ',
                        'address_delete' => 'Xóa Địa Chỉ',
                        'data_export' => 'Xuất Dữ Liệu',
                        'account_deletion' => 'Xóa Tài Khoản',
                    ])
                    ->label('Hành động'),
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
