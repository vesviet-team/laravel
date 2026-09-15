<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Seller\Resources\SimpleProductResource\Pages;
use App\Models\Product;
use App\Policies\SellerProductPolicy;
use App\Services\AiCopywriterService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Simple Product Resource for the Seller Panel.
 *
 * Authorization: SellerProductPolicy (registered in AppServiceProvider via Gate::policy)
 * Tenant Scoping: TenantSellerScope applied globally via BelongsToSeller trait,
 *   plus explicit getEloquentQuery() override for defense-in-depth (SF-10).
 *
 * ADR-S3 Trust Zone: Standard
 */
class SimpleProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'Sản phẩm';

    protected static ?string $pluralModelLabel = 'Sản phẩm';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Sản phẩm';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin cơ bản')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Tên sản phẩm')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Đường dẫn (Slug)')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                Product::class,
                                'slug',
                                ignoreRecord: true
                            ),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('Ảnh sản phẩm')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->directory(fn () => 'sellers/'.(Filament::getTenant()?->id ?? 'default').'/products')
                            ->visibility('public'),

                        Forms\Components\RichEditor::make('description')
                            ->label('Mô tả sản phẩm')
                            ->hintAction(
                                Forms\Components\Actions\Action::make('ai_copywriter')
                                    ->label('✨ AI Viết hộ')
                                    ->icon('heroicon-o-sparkles')
                                    ->action(function (Forms\Set $set, Forms\Get $get) {
                                        $productName = $get('name');
                                        if (! $productName) {
                                            return;
                                        }

                                        $service = app(AiCopywriterService::class);
                                        $set('description', $service->generateProductDescription($productName));
                                    })
                            ),
                    ]),

                Forms\Components\Section::make('Giá & Kho')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Giá bán (VND)')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('compare_at_price')
                            ->label('Giá gốc gạch ngang (VND)')
                            ->numeric(),

                        Forms\Components\TextInput::make('stock')
                            ->label('Số lượng có sẵn')
                            ->numeric()
                            ->default(100)
                            ->required(),
                    ]),

                Forms\Components\Section::make('Hiển thị & Trạng thái')
                    ->schema([
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Hiển thị trên trang bán hàng')
                            ->helperText('Tắt để ẩn sản phẩm khỏi trang của bạn')
                            ->default(true),

                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'draft' => 'Bản nháp',
                                'published' => 'Đã xuất bản',
                                'archived' => 'Đã lưu trữ',
                            ])
                            ->default('published')
                            ->required(),

                        Forms\Components\Toggle::make('is_purchasable')
                            ->label('Cho phép mua hàng')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Sản phẩm nổi bật')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Hình ảnh')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên sản phẩm')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Giá bán')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Kho')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'archived' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Bản nháp',
                        'published' => 'Đã xuất bản',
                        'archived' => 'Đã lưu trữ',
                        default => $state,
                    }),
                Tables\Columns\ToggleColumn::make('is_visible')
                    ->label('Hiển thị'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Bản nháp',
                        'published' => 'Đã xuất bản',
                        'archived' => 'Đã lưu trữ',
                    ]),
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

    /**
     * Explicitly scope the resource query to the current Filament tenant.
     *
     * Defense-in-depth (SF-10): TenantSellerScope already filters by seller_id
     * when a Spatie Tenant is current, but this override ensures that even if
     * the global scope is bypassed or no tenant is set, the resource never leaks
     * cross-seller product data.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $tenantId = Filament::getTenant()?->id;

        if ($tenantId !== null) {
            $query->where('seller_id', $tenantId);
        } else {
            // No active tenant — return empty result set rather than leaking all products.
            $query->whereRaw('1 = 0');
        }

        return $query;
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
            'index' => Pages\ListSimpleProducts::route('/'),
            'create' => Pages\CreateSimpleProduct::route('/create'),
            'edit' => Pages\EditSimpleProduct::route('/{record}/edit'),
        ];
    }
}
