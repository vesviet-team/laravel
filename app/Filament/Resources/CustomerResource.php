<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\ReferralsRelationManager;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Thông Tin Cơ Bản')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Họ và tên')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone')
                    ->label('Số điện thoại')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\DatePicker::make('date_of_birth')
                    ->label('Ngày sinh'),

                Forms\Components\Select::make('gender')
                    ->label('Giới tính')
                    ->options([
                        'male' => 'Nam',
                        'female' => 'Nữ',
                        'other' => 'Khác',
                    ]),
            ])->columns(2),

            Forms\Components\Section::make('Tài Khoản & Bảo Mật')->schema([
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Hoạt động',
                        'inactive' => 'Không hoạt động',
                        'deleted' => 'Đã xóa',
                    ])
                    ->default('active')
                    ->required(),

                Forms\Components\Toggle::make('two_factor_enabled')
                    ->label('2FA')
                    ->disabled(),

                Forms\Components\TextInput::make('email_verified_at')
                    ->label('Email xác thực lúc')
                    ->disabled()
                    ->placeholder('Chưa xác thực'),

                Forms\Components\TextInput::make('loyalty_points')
                    ->label('Điểm thưởng')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ])->columns(2),

            Forms\Components\Section::make('Giới Thiệu')->schema([
                Forms\Components\TextInput::make('referral_code')
                    ->label('Mã giới thiệu')
                    ->disabled()
                    ->placeholder('Tự động tạo'),

                Forms\Components\TextInput::make('referred_by')
                    ->label('Được giới thiệu bởi (ID)')
                    ->disabled()
                    ->numeric(),

                Forms\Components\TextInput::make('total_spent')
                    ->label('Tổng chi tiêu (VNĐ)')
                    ->disabled()
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.').'₫' : '0₫'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                Tables\Columns\TextColumn::make('name')
                    ->label('Họ và tên')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Đã sao chép email')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Đã sao chép SĐT')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Hoạt động',
                        'inactive' => 'Không hoạt động',
                        'deleted' => 'Đã xóa',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->label('2FA')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('loyalty_points')
                    ->label('Điểm thưởng')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => number_format((int) $state).'đ')
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('orders_sum_total_amount')
                    ->label('Tổng chi tiêu')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.').'₫' : '0₫')
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('membership_tier')
                    ->label('Hạng thành viên')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'VIP Diamond' => 'warning',
                        'Thành Viên Thân Thiết' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('referral_code')
                    ->label('Mã giới thiệu')
                    ->copyable()
                    ->copyMessage('Đã sao chép mã')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Đăng nhập gần nhất')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Chưa đăng nhập'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Hoạt động',
                        'inactive' => 'Không hoạt động',
                        'deleted' => 'Đã xóa',
                    ])
                    ->label('Trạng thái'),

                Tables\Filters\SelectFilter::make('two_factor_enabled')
                    ->options([
                        '1' => 'Đã bật 2FA',
                        '0' => 'Chưa bật 2FA',
                    ])
                    ->label('Xác thực 2 yếu tố'),

                Tables\Filters\SelectFilter::make('membership_tier')
                    ->options([
                        'VIP Diamond' => 'VIP Diamond',
                        'Thành Viên Thân Thiết' => 'Thành Viên Thân Thiết',
                        'Thành Viên Mới' => 'Thành Viên Mới',
                    ])
                    ->label('Hạng thành viên'),

                Tables\Filters\Filter::make('has_referrals')
                    ->label('Có giới thiệu')
                    ->query(fn ($query) => $query->whereHas('referrals')),

                Tables\Filters\Filter::make('referred')
                    ->label('Được giới thiệu')
                    ->query(fn ($query) => $query->whereNotNull('referred_by')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('enable_2fa')
                        ->label('Bật 2FA')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn ($record) => $record->update(['two_factor_enabled' => true])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('disable_2fa')
                        ->label('Tắt 2FA')
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn ($record) => $record->update(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('force_logout')
                        ->label('Đăng xuất tất cả thiết bị')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(function ($record) {
                            $record->active_sessions = [];
                            $record->saveQuietly();
                        }))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('reset_loyalty_points')
                        ->label('Đặt lại điểm thưởng')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn ($record) => $record->update(['loyalty_points' => 0])))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSum('orders', 'total_amount')
            ->withCount(['referrals', 'addresses', 'auditLogs'])
            ->with('referrer');
    }

    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
            AuditLogsRelationManager::class,
            ReferralsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
        ];
    }
}
