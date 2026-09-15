<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use App\Rules\ReservedRouteRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Blog & CMS';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Nội Dung Bài Viết')->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Tiêu đề bài viết')
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
                            ->helperText('Đường dẫn URL thân thiện SEO (vd: xu-huong-noi-that-bac-au-2026).'),

                        Forms\Components\Textarea::make('excerpt')
                            ->label('Tóm tắt ngắn (Excerpt)')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Tóm tắt ngắn (150-160 ký tự) hiển thị trên card bài viết, kết quả tìm kiếm và mạng xã hội.'),

                        Forms\Components\RichEditor::make('body')
                            ->label('Nội dung bài viết')
                            ->required()
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('posts/attachments')
                            ->helperText('Soạn thảo nội dung bài viết với đầy đủ tiêu đề H2/H3 (để auto-gen Mục lục TOC) và hình ảnh.'),

                        Forms\Components\FileUpload::make('featured_image')
                            ->label('Ảnh đại diện bài viết (Featured Image)')
                            ->image()
                            ->disk('public')
                            ->directory('posts')
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->previewable(true)
                            ->maxSize(10240)
                            ->helperText('Ảnh chất lượng cao hiển thị ở đầu bài viết và trên danh sách blog.'),
                    ])->columns(2),

                    Forms\Components\Section::make('Contextual Commerce (Gắn Sản Phẩm Nội Thất)')->schema([
                        Forms\Components\Select::make('products')
                            ->label('Sản phẩm nội thất liên quan')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Chọn các sản phẩm nội thất Sober liên quan để tự động hiển thị trên widget mua sắm cuối bài viết và card sản phẩm nổi bật ở sidebar.'),
                    ]),

                    Forms\Components\Section::make('SEO & Dữ Liệu Cấu Trúc (Schema.org / Social Share)')->schema([
                        Forms\Components\TextInput::make('seo_title')
                            ->label('Tiêu đề SEO (Title Tag)')
                            ->maxLength(255)
                            ->helperText('Tùy chỉnh thẻ Title cho công cụ tìm kiếm (mặc định lấy tiêu đề bài viết).'),

                        Forms\Components\Textarea::make('seo_description')
                            ->label('Mô tả SEO (Meta Description)')
                            ->rows(2)
                            ->maxLength(255)
                            ->helperText('Thẻ meta description cho SEO.'),

                        Forms\Components\TextInput::make('canonical_url')
                            ->label('Canonical URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL Canonical nếu bài viết lấy từ nguồn khác.'),

                        Forms\Components\FileUpload::make('og_image')
                            ->label('Ảnh OpenGraph / Social Share')
                            ->image()
                            ->disk('public')
                            ->directory('posts/og')
                            ->maxSize(10240)
                            ->helperText('Ảnh chia sẻ Facebook/Zalo (tỷ lệ chuẩn 1200x630px).'),

                        Forms\Components\Select::make('schema_type')
                            ->label('Loại dữ liệu cấu trúc (Schema Type)')
                            ->options([
                                'BlogPosting' => 'BlogPosting (Bài viết Blog)',
                                'Article' => 'Article (Bài báo / Kiến thức)',
                                'NewsArticle' => 'NewsArticle (Tin tức)',
                            ])
                            ->default('BlogPosting')
                            ->required(),

                        Forms\Components\Repeater::make('faq_schema')
                            ->label('FAQ Schema (Tối ưu AEO / Trả lời bằng giọng nói / Google FAQ Rich Snippet)')
                            ->schema([
                                Forms\Components\TextInput::make('question')->required()->label('Câu hỏi'),
                                Forms\Components\Textarea::make('answer')->required()->rows(2)->label('Câu trả lời'),
                            ])
                            ->collapsible()
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])->columns(2)->collapsible(),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Cài Đặt Xuất Bản')->schema([
                        Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'draft' => 'Draft (Bản nháp)',
                                'published' => 'Published (Đã xuất bản)',
                                'scheduled' => 'Scheduled (Hẹn giờ)',
                                'archived' => 'Archived (Lưu trữ)',
                            ])
                            ->required()
                            ->default('draft'),

                        Forms\Components\Select::make('post_category_id')
                            ->label('Danh mục bài viết')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('user_id')
                            ->label('Tác giả')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id()),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Thời gian xuất bản')
                            ->default(now())
                            ->helperText('Bài viết có thời gian trong tương lai sẽ ở trạng thái chờ xuất bản.'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Bài viết nổi bật')
                            ->helperText('Hiển thị trên Hero Card trang chủ Blog.')
                            ->default(false),

                        Forms\Components\TextInput::make('reading_time_minutes')
                            ->label('Thời gian đọc (phút)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Tự động tính từ số lượng từ (~200 wpm).'),

                        Forms\Components\TextInput::make('view_count')
                            ->label('Lượt xem')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->default(0),
                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Ảnh bìa')
                    ->disk('public')
                    ->height(45)
                    ->width(72)
                    ->extraImgAttributes(['class' => 'object-cover rounded-md shadow-xs']),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề bài viết')
                    ->searchable()
                    ->sortable()
                    ->grow()
                    ->weight('medium')
                    ->description(fn ($record) => Str::limit($record->excerpt, 80))
                    ->tooltip(fn ($record) => $record->title),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'scheduled' => 'info',
                        'archived' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Nổi bật'),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Ngày xuất bản')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Tác giả')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reading_time_minutes')
                    ->label('Thời gian đọc')
                    ->suffix(' phút')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('post_category_id')
                    ->label('Danh mục')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'scheduled' => 'Scheduled',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Nổi bật'),

                Tables\Filters\Filter::make('published_at')
                    ->form([
                        Forms\Components\DatePicker::make('published_from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('published_until')->label('Đến ngày'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['published_from'] ?? null, fn ($q, $d) => $q->whereDate('published_at', '>=', $d))
                        ->when($data['published_until'] ?? null, fn ($q, $d) => $q->whereDate('published_at', '<=', $d))
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Xem trước')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Post $record): string => url('/blog/'.$record->slug))
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
