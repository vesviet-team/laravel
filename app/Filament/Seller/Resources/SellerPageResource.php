<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Seller\Resources\SellerPageResource\Pages;
use App\Models\SellerPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SellerPageResource extends Resource
{
    protected static ?string $model = SellerPage::class;

    protected static ?string $modelLabel = 'Trang cửa hàng';

    protected static ?string $pluralModelLabel = 'Trang cửa hàng';

    protected static ?string $navigationIcon = 'heroicon-o-window';

    // We only need one page per seller, so we can use a custom approach or standard resource
    // For a single page, often a Page component is better than a Resource, but Resource works if we restrict creation.

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Split::make([
                    Forms\Components\Section::make('Cấu hình trang (Tương tự Carrd)')
                        ->schema([
                            Forms\Components\Tabs::make('Tabs')
                                ->tabs([
                                    Forms\Components\Tabs\Tab::make('Giao diện')
                                        ->icon('heroicon-o-paint-brush')
                                        ->schema([
                                            Forms\Components\ColorPicker::make('theme_config.primary_color')
                                                ->label('Màu chủ đạo')
                                                ->default('#3b82f6'),
                                            Forms\Components\Select::make('theme_config.font')
                                                ->label('Font chữ')
                                                ->options([
                                                    'Inter' => 'Inter',
                                                    'Roboto' => 'Roboto',
                                                    'Playfair Display' => 'Playfair Display',
                                                ])
                                                ->default('Inter'),
                                            Forms\Components\Select::make('theme_config.mode')
                                                ->label('Chế độ (Sáng/Tối)')
                                                ->options([
                                                    'light' => 'Sáng',
                                                    'dark' => 'Tối',
                                                ])
                                                ->default('light'),
                                        ]),

                                    Forms\Components\Tabs\Tab::make('Nội dung (Blocks)')
                                        ->icon('heroicon-o-bars-3-bottom-left')
                                        ->schema([
                                            Forms\Components\Builder::make('blocks')
                                                ->label('Các thành phần trang')
                                                ->blocks([
                                                    Forms\Components\Builder\Block::make('hero')
                                                        ->label('Hero / Banner')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('title')->label('Tiêu đề chính')->required(),
                                                            Forms\Components\TextInput::make('subtitle')->label('Tiêu đề phụ'),
                                                            Forms\Components\FileUpload::make('background_image')->label('Ảnh nền')->image(),
                                                        ]),
                                                    Forms\Components\Builder\Block::make('products')
                                                        ->label('Danh sách Sản phẩm')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('title')->label('Tiêu đề phần')->default('Sản phẩm nổi bật'),
                                                            Forms\Components\Select::make('limit')
                                                                ->label('Số lượng hiển thị')
                                                                ->options([4 => 4, 8 => 8, 12 => 12])
                                                                ->default(8),
                                                        ]),
                                                    Forms\Components\Builder\Block::make('media')
                                                        ->label('Hình ảnh / Video')
                                                        ->schema([
                                                            Forms\Components\FileUpload::make('image')->image(),
                                                            Forms\Components\TextInput::make('youtube_url')->url()->label('Hoặc link Youtube'),
                                                        ]),
                                                    Forms\Components\Builder\Block::make('faq')
                                                        ->label('Hỏi đáp (FAQ)')
                                                        ->schema([
                                                            Forms\Components\Repeater::make('questions')
                                                                ->schema([
                                                                    Forms\Components\TextInput::make('q')->label('Hỏi')->required(),
                                                                    Forms\Components\Textarea::make('a')->label('Đáp')->required(),
                                                                ]),
                                                        ]),
                                                    Forms\Components\Builder\Block::make('socials')
                                                        ->label('Mạng xã hội')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('facebook')->url(),
                                                            Forms\Components\TextInput::make('instagram')->url(),
                                                            Forms\Components\TextInput::make('tiktok')->url(),
                                                        ]),
                                                ])
                                                ->collapsible()
                                                ->cloneable(),
                                        ]),
                                ]),

                            Forms\Components\Toggle::make('is_published')
                                ->label('Xuất bản trang (Công khai)')
                                ->default(false),
                        ])->grow(true),

                    Forms\Components\Section::make('Xem trước (Preview)')
                        ->schema([
                            Forms\Components\View::make('filament.seller.components.mobile-preview-frame'),
                        ])->grow(false)->extraAttributes(['style' => 'min-width: 350px; width: 350px;']),
                ])->from('md'),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('seller.shop_name')
                    ->label('Cửa hàng'),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Trạng thái')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_live')
                    ->label('Xem trang trực tiếp')
                    ->url(fn (SellerPage $record): string => 'https://'.$record->seller->subdomain.'.'.config('app.url'))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerPages::route('/'),
            'edit' => Pages\EditSellerPage::route('/{record}/edit'),
        ];
    }
}
