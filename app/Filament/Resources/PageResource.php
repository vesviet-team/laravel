<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use App\Rules\ReservedRouteRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static ?string $navigationGroup = 'Blog & CMS';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông Tin Trang')->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Tiêu đề trang')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                    Forms\Components\TextInput::make('slug')
                        ->label('Đường dẫn (Slug)')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->rules([new ReservedRouteRule])
                        ->helperText('Đường dẫn trang tĩnh: ví dụ chinh-sach-bao-hanh sẽ tương ứng với URL /chinh-sach-bao-hanh. Không được trùng với route hệ thống.'),

                    Forms\Components\Select::make('template')
                        ->label('Giao diện mẫu (Template)')
                        ->options([
                            'default' => 'Trang tiêu chuẩn (Standard Page)',
                            'policy' => 'Trang chính sách / Pháp lý (Policy / Legal)',
                            'full_width' => 'Toàn chiều rộng (Full Width)',
                        ])
                        ->default('default')
                        ->required(),

                    Forms\Components\Toggle::make('is_published')
                        ->label('Xuất bản (Công khai)')
                        ->default(true),

                    Forms\Components\RichEditor::make('body')
                        ->label('Nội dung trang')
                        ->required()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages/attachments')
                        ->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make('Cài Đặt SEO & Social Share')->schema([
                    Forms\Components\TextInput::make('seo_title')
                        ->label('Tiêu đề SEO')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('seo_description')
                        ->label('Mô tả SEO')
                        ->rows(2)
                        ->maxLength(255),

                    Forms\Components\FileUpload::make('og_image')
                        ->label('Ảnh Social Share')
                        ->image()
                        ->disk('public')
                        ->directory('pages/og')
                        ->maxSize(10240),
                ])->columns(2)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề trang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug / URL')
                    ->searchable(),

                Tables\Columns\TextColumn::make('template')
                    ->label('Giao diện')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Xuất bản')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật lần cuối')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Xuất bản'),

                Tables\Filters\SelectFilter::make('template')
                    ->label('Giao diện')
                    ->options([
                        'default' => 'Default',
                        'policy' => 'Policy',
                        'full_width' => 'Full Width',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Xem trang')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $record): string => url('/'.$record->slug))
                    ->openUrlInNewTab()
                    ->color('gray'),
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
