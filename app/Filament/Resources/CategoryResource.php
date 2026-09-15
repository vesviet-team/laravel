<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Category Information')->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('parent_id')
                            ->label('Danh mục cha')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('slug')->required()->unique(),
                            ])
                            ->helperText('Để trống nếu đây là danh mục gốc (root).'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Thứ tự hiển thị')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\MarkdownEditor::make('description')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'strike', 'link',
                                'bulletList', 'orderedList', 'blockquote',
                            ]),
                    ])->columns(2),

                    Forms\Components\Section::make('Hình Ảnh')->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Ảnh banner / thumbnail')
                            ->image()
                            ->disk('public')
                            ->directory('categories')
                            ->visibility('public')
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->previewable(true)
                            ->maxSize(5120)
                            ->columnSpanFull()
                            ->helperText('Ảnh hiển thị cho danh mục này trên catalog.'),
                    ]),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('SEO & Meta')->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(255)
                            ->helperText('Để trống sẽ dùng tên danh mục.'),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(3)
                            ->maxLength(160)
                            ->helperText('Để trống sẽ dùng mô tả danh mục. Tối đa 160 ký tự.'),
                        Forms\Components\TextInput::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->maxLength(255)
                            ->helperText('Từ khóa cách nhau bằng dấu phẩy.'),
                    ]),

                    Forms\Components\Section::make('Visibility')->schema([
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Hiển thị trong navigation')
                            ->default(true)
                            ->helperText('Tắt để ẩn khỏi menu nhưng vẫn truy cập được qua link trực tiếp.'),
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
                    ->size(50),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên danh mục')
                    ->searchable()
                    ->weight('medium')
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Đã copy slug')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Danh mục cha')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('— (Root)'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('level')
                    ->label('Cấp độ')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === 0 => 'primary',
                        $state === 1 => 'info',
                        $state === 2 => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Hiển thị')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_products_count')
                    ->label('Sản phẩm active')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent')
                    ->label('Danh mục cha')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Hiển thị')
                    ->placeholder('Tất cả')
                    ->trueLabel('Có')
                    ->falseLabel('Không'),
                Tables\Filters\Filter::make('root_only')
                    ->label('Chỉ danh mục gốc')
                    ->query(fn ($query) => $query->whereNull('parent_id')),
                Tables\Filters\Filter::make('has_products')
                    ->label('Có sản phẩm')
                    ->query(fn ($query) => $query->whereHas('activeProducts')),
                Tables\Filters\Filter::make('empty')
                    ->label('Không có sản phẩm')
                    ->query(fn ($query) => $query->doesntHave('activeProducts')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('duplicate')
                        ->label('Nhân bản')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(fn (Category $record) => $record->replicate()->push())
                        ->requiresConfirmation(),
                    Tables\Actions\Action::make('view_products')
                        ->label('Xem sản phẩm')
                        ->icon('heroicon-o-cube')
                        ->url(fn (Category $record) => route('products.index', ['category' => $record->slug]))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('show')
                        ->label('Hiện')
                        ->icon('heroicon-o-eye')
                        ->action(fn ($records) => $records->each->update(['is_visible' => true])),
                    Tables\Actions\BulkAction::make('hide')
                        ->label('Ẩn')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn ($records) => $records->each->update(['is_visible' => false])),
                    Tables\Actions\BulkAction::make('reorder')
                        ->label('Sắp xếp lại (đặt sort_order = ID)')
                        ->icon('heroicon-o-arrows-right-left')
                        ->action(fn ($records) => $records->each->update(['sort_order' => $records->pluck('id')->search(fn ($id) => $id)])),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->defaultSort('name', 'asc')
            ->paginated([20, 50, 100])
            ->striped();
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }
}
