<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // CỘT CHÍNH (Chiếm 2/3 bề ngang trên màn hình lớn)
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Nội Dung & Hình Ảnh Banner')->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Tiêu đề banner')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Tiêu đề chính hiển thị nổi bật trên banner.'),

                        Forms\Components\TextInput::make('eyebrow')
                            ->label('Tagline / Eyebrow')
                            ->maxLength(255)
                            ->helperText('Dòng chữ nhỏ phong cách nằm phía trên tiêu đề chính (ví dụ: "BỘ SƯU TẬP MÙA HÈ 2026").'),

                        Forms\Components\Textarea::make('subtitle')
                            ->label('Phụ đề / Mô tả ngắn')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Đoạn văn ngắn mô tả thêm hoặc kêu gọi hành động.'),

                        Forms\Components\FileUpload::make('image')
                            ->label('Hình ảnh banner')
                            ->image()
                            ->disk('public')
                            ->directory('banners')
                            ->visibility('public')
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->previewable(true)
                            ->maxSize(10240)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->columnSpanFull()
                            ->helperText('Tải lên ảnh chất lượng cao (tối đa 10MB). Khuyến nghị tỷ lệ 16:9 hoặc theo vị trí hiển thị.'),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('link')
                                ->label('Đường dẫn liên kết (URL)')
                                ->maxLength(255)
                                ->helperText('Link chuyển hướng khi click banner (vd: /products hoặc https://...).'),

                            Forms\Components\TextInput::make('cta_text')
                                ->label('Chữ nút bấm (CTA Text)')
                                ->default('Khám Phá Ngay')
                                ->maxLength(255)
                                ->helperText('Nội dung hiển thị trên nút bấm (mặc định: "Khám Phá Ngay").'),
                        ]),

                        Forms\Components\Toggle::make('open_in_new_tab')
                            ->label('Mở liên kết trong tab mới (_blank)')
                            ->default(false)
                            ->helperText('Bật nếu muốn mở liên kết ở trang mới khi người dùng click.'),
                    ]),
                ])->columnSpan(['lg' => 2]),

                // SIDEBAR THIẾT LẬP (Chiếm 1/3 bề ngang)
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Phân Loại & Trạng Thái')->schema([
                        Forms\Components\Select::make('position')
                            ->label('Vị trí hiển thị')
                            ->options(Banner::POSITIONS)
                            ->default('hero_slider')
                            ->required()
                            ->searchable()
                            ->helperText('Chọn khu vực hiển thị banner.'),

                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'active' => 'Active (Hoạt động)',
                                'inactive' => 'Inactive (Tạm ẩn)',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Thứ tự sắp xếp')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Số nhỏ hơn sẽ hiển thị trước (hoặc kéo thả trên danh sách).'),
                    ]),

                    Forms\Components\Section::make('Lịch Hiển Thị Tự Động')->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Bắt đầu hiển thị')
                            ->helperText('Để trống nếu muốn hiển thị ngay.'),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Kết thúc hiển thị')
                            ->helperText('Để trống nếu không giới hạn thời gian.'),
                    ]),

                    Forms\Components\Section::make('Đo Lường & Hiệu Suất')->schema([
                        Forms\Components\TextInput::make('clicks_count')
                            ->label('Tổng lượt click')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->default(0)
                            ->suffix('lượt')
                            ->helperText('Số lần người dùng nhấp vào banner này qua route theo dõi.'),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Ảnh')
                    ->disk('public')
                    ->height(45)
                    ->width(75)
                    ->extraImgAttributes(['class' => 'object-cover rounded shadow-sm'])
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề banner')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Banner $record): ?string => $record->eyebrow ?: ($record->subtitle ? Str::limit($record->subtitle, 45) : null)),

                Tables\Columns\TextColumn::make('position')
                    ->label('Vị trí')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'hero_slider' => '🌟 Slide Trang Chủ',
                        'home_promo_2col' => '🏷️ Khuyến Mãi 2 Cột',
                        'home_collection_3col' => '🛋️ Bộ Sưu Tập 3 Cột',
                        'catalog_header' => '📦 Header Catalog',
                        'blog_sidebar' => '📰 Blog Sidebar',
                        'top_announcement' => '📢 Thông Báo Header',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'hero_slider' => 'primary',
                        'home_promo_2col' => 'warning',
                        'home_collection_3col' => 'info',
                        'catalog_header' => 'success',
                        'blog_sidebar' => 'gray',
                        'top_announcement' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('clicks_count')
                    ->label('Lượt click')
                    ->numeric()
                    ->sortable()
                    ->suffix(' lượt')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Hoạt động',
                        'inactive' => 'Tạm ẩn',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Bắt đầu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Kết thúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('position')
                    ->label('Vị trí')
                    ->options(Banner::POSITIONS),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Hoạt động',
                        'inactive' => 'Tạm ẩn',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Xem liên kết')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Banner $record): string => $record->link ?: '#')
                    ->openUrlInNewTab()
                    ->color('gray')
                    ->visible(fn (Banner $record): bool => filled($record->link)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
