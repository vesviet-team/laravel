<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Product Information')->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\MarkdownEditor::make('description')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'codeBlock',
                                'blockquote',
                            ]),
                    ])->columns(2),

                    Forms\Components\Section::make('Pricing & Inventory')->schema([
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix('₫')
                            ->minValue(0)
                            ->step(100),
                        Forms\Components\TextInput::make('compare_at_price')
                            ->label('Giá gốc (để hiển thị giảm giá)')
                            ->numeric()
                            ->prefix('₫')
                            ->minValue(0)
                            ->step(100)
                            ->helperText('Điền giá gốc nếu sản phẩm đang giảm giá.'),
                        Forms\Components\TextInput::make('stock')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\TextInput::make('low_stock_threshold')
                            ->label('Ngưỡng cảnh báo hết hàng')
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->helperText('Hệ thống sẽ cảnh báo khi tồn kho dưới ngưỡng này.'),
                    ])->columns(4),

                    Forms\Components\Section::make('Physical Attributes (for Shipping)')->schema([
                        Forms\Components\TextInput::make('weight')
                            ->label('Trọng lượng (gram)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Dùng cho tính phí ship. 0 = tự động dùng 1000g.'),
                        Forms\Components\TextInput::make('length')
                            ->label('Dài (cm)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\TextInput::make('width')
                            ->label('Rộng (cm)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\TextInput::make('height')
                            ->label('Cao (cm)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])->columns(4),

                    Forms\Components\Section::make('Hình Ảnh Sản Phẩm (Thumbnail & Gallery)')->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Ảnh đại diện (Thumbnail / Main Image)')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->previewable(true)
                            ->maxSize(10240)
                            ->columnSpanFull()
                            ->helperText('Ảnh đại diện chính của sản phẩm.'),

                        Forms\Components\FileUpload::make('attributes_json.gallery')
                            ->label('Bộ sưu tập ảnh (Gallery Images)')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->disk('public')
                            ->directory('products/gallery')
                            ->visibility('public')
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->previewable(true)
                            ->panelLayout('grid')
                            ->maxSize(10240)
                            ->columnSpanFull()
                            ->helperText('Tải lên nhiều góc ảnh để hiển thị dải thumbnail trên trang chi tiết sản phẩm.'),
                    ]),

                    Forms\Components\Section::make('Product Variants')->schema([
                        Forms\Components\Repeater::make('variants')
                            ->relationship('variants')
                            ->schema([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('sku')->label('SKU')->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('barcode')->label('Barcode (EAN/UPC)')->maxLength(50),
                                Forms\Components\TextInput::make('price')->numeric()->required()->prefix('₫')->minValue(0),
                                Forms\Components\TextInput::make('compare_at_price')->label('Giá gốc')->numeric()->prefix('₫')->minValue(0),
                                Forms\Components\TextInput::make('stock')->numeric()->required()->default(0)->minValue(0),
                                Forms\Components\TextInput::make('low_stock_threshold')->label('Ngưỡng cảnh báo')->numeric()->default(5)->minValue(1),
                                Forms\Components\TextInput::make('position')->label('Thứ tự hiển thị')->numeric()->default(0)->minValue(0),
                                Forms\Components\Toggle::make('is_active')->label('Hoạt động')->default(true),
                                Forms\Components\Toggle::make('is_purchasable')->label('Có thể mua')->default(true),
                                Forms\Components\KeyValue::make('option_values')
                                    ->label('Option Values (e.g., Color: Red, Size: M)')
                                    ->keyLabel('Attribute')
                                    ->valueLabel('Value')
                                    ->addActionLabel('Thêm thuộc tính'),
                                Forms\Components\KeyValue::make('attributes_json')
                                    ->label('Additional Attributes')
                                    ->formatStateUsing(function ($state) {
                                        if (! is_array($state)) {
                                            return $state;
                                        }

                                        return array_map(fn ($val) => is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $val, $state);
                                    })
                                    ->mutateDehydratedStateUsing(function ($state) {
                                        if (! is_array($state)) {
                                            return $state;
                                        }

                                        return array_map(function ($val) {
                                            if (is_string($val)) {
                                                $decoded = json_decode($val, true);
                                                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                                                    return $decoded;
                                                }
                                            }

                                            return $val;
                                        }, $state);
                                    }),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),

                    Forms\Components\Section::make('Tags & Attributes')->schema([
                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags (Color, Material, Style, etc.)')
                            ->placeholder('Nhấn Enter để thêm tag')
                            ->columnSpanFull()
                            ->helperText('Các tag dùng cho bộ lọc mặt (faceted navigation) trên catalog.'),
                        Forms\Components\KeyValue::make('attributes_json')
                            ->label('Thuộc tính kỹ thuật (JSON)')
                            ->keyLabel('Tên thuộc tính')
                            ->valueLabel('Giá trị')
                            ->addActionLabel('Thêm thuộc tính')
                            ->columnSpanFull()
                            ->helperText('VD: material: "Oak", finish: "Natural oil", load_capacity: "120kg".'),
                    ]),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Status & Category')->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'published' => 'Published (Đã xuất bản)',
                                'draft' => 'Draft (Bản nháp)',
                                'archived' => 'Archived (Lưu trữ)',
                            ])
                            ->required()
                            ->default('published'),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Sản phẩm nổi bật')
                            ->helperText('Hiển thị trên trang chủ')
                            ->default(false),
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Hiển thị trong catalog')
                            ->default(true)
                            ->helperText('Tắt để ẩn khỏi catalog nhưng vẫn truy cập được qua link trực tiếp.'),
                        Forms\Components\Toggle::make('is_purchasable')
                            ->label('Cho phép mua')
                            ->default(true)
                            ->helperText('Tắt để hiển thị nhưng không cho thêm vào giỏ hàng.'),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('slug')->required()->unique(),
                                Forms\Components\Select::make('parent_id')
                                    ->relationship('parent', 'name')
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ]),

                    Forms\Components\Section::make('Publishing')->schema([
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Ngày xuất bản')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->helperText('Để trống = tự động đặt khi chuyển sang Published.'),
                    ]),

                    Forms\Components\Section::make('SEO & Meta')->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(255)
                            ->helperText('Để trống sẽ dùng tên sản phẩm.'),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(3)
                            ->maxLength(160)
                            ->helperText('Để trống sẽ dùng mô tả sản phẩm. Tối đa 160 ký tự.'),
                        Forms\Components\TextInput::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->maxLength(255)
                            ->helperText('Từ khóa cách nhau bằng dấu phẩy.'),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Ảnh')
                    ->circular()
                    ->size(50),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên sản phẩm')
                    ->searchable()
                    ->weight('medium')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 50 ? $state : null;
                    }),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Đã copy SKU'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Giá bán')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.').'₫')
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('compare_at_price')
                    ->label('Giá gốc')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.').'₫' : '—')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Tồn kho')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 5 ? 'warning' : 'success'))
                    ->badge(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Nổi bật')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Hiển thị')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_purchasable')
                    ->label('Mua được')
                    ->boolean()
                    ->trueIcon('heroicon-o-shopping-cart')
                    ->falseIcon('heroicon-o-shopping-cart')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Ngày xuất bản')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Sản phẩm nổi bật')
                    ->placeholder('Tất cả')
                    ->trueLabel('Có')
                    ->falseLabel('Không'),
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Hiển thị trong catalog')
                    ->placeholder('Tất cả')
                    ->trueLabel('Có')
                    ->falseLabel('Không'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Sắp hết hàng')
                    ->query(fn ($query) => $query->whereRaw('stock > 0 AND stock <= low_stock_threshold')),
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Hết hàng')
                    ->query(fn ($query) => $query->where('stock', '<=', 0)),
                Tables\Filters\Filter::make('on_sale')
                    ->label('Đang giảm giá')
                    ->query(fn ($query) => $query->whereNotNull('compare_at_price')->whereRaw('compare_at_price > price')),
                Tables\Filters\Filter::make('new_arrivals')
                    ->label('Sản phẩm mới (30 ngày)')
                    ->query(fn ($query) => $query->where('published_at', '>=', now()->subDays(30))),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->url(fn (Product $record): string => route('products.show', $record->slug))
                        ->openUrlInNewTab(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('duplicate')
                        ->label('Nhân bản')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(fn (Product $record) => $record->replicate()->push())
                        ->requiresConfirmation(),
                    Tables\Actions\Action::make('toggle_featured')
                        ->label(fn (Product $record): string => $record->is_featured ? 'Bỏ nổi bật' : 'Đặt nổi bật')
                        ->icon(fn (Product $record): string => $record->is_featured ? 'heroicon-o-star' : 'heroicon-o-star')
                        ->color(fn (Product $record): string => $record->is_featured ? 'warning' : 'primary')
                        ->action(fn (Product $record) => $record->update(['is_featured' => ! $record->is_featured])),
                    Tables\Actions\Action::make('toggle_visible')
                        ->label(fn (Product $record): string => $record->is_visible ? 'Ẩn khỏi catalog' : 'Hiện trong catalog')
                        ->icon(fn (Product $record): string => $record->is_visible ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->color(fn (Product $record): string => $record->is_visible ? 'gray' : 'primary')
                        ->action(fn (Product $record) => $record->update(['is_visible' => ! $record->is_visible])),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Xuất bản')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'published', 'published_at' => now()])),
                    Tables\Actions\BulkAction::make('draft')
                        ->label('Chuyển sang bản nháp')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['status' => 'draft'])),
                    Tables\Actions\BulkAction::make('archive')
                        ->label('Lưu trữ')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['status' => 'archived'])),
                    Tables\Actions\BulkAction::make('feature')
                        ->label('Đặt nổi bật')
                        ->icon('heroicon-o-star')
                        ->action(fn ($records) => $records->each->update(['is_featured' => true])),
                    Tables\Actions\BulkAction::make('unfeature')
                        ->label('Bỏ nổi bật')
                        ->icon('heroicon-o-star')
                        ->action(fn ($records) => $records->each->update(['is_featured' => false])),
                    Tables\Actions\BulkAction::make('hide')
                        ->label('Ẩn khỏi catalog')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn ($records) => $records->each->update(['is_visible' => false])),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([12, 24, 48, 96])
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            // Relation managers can be added here
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'description'];
    }
}
