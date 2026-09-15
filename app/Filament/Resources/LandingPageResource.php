<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LandingPageResource\Pages;
use App\Models\LandingPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LandingPageResource extends Resource
{
    protected static ?string $model = LandingPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->notIn(['admin', 'cart', 'checkout', 'login', 'register', 'api', 'products', 'about', 'contact', 'track-order', 'account', 'newsletter', 'wishlist', 'order-tracking', 'password'])
                                    ->validationMessages([
                                        'not_in' => 'Slug này trùng với đường dẫn hệ thống đã được đặt trước.',
                                    ]),
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                            ]),
                        Forms\Components\Tabs\Tab::make('Content')
                            ->schema([
                                Forms\Components\RichEditor::make('content')->columnSpanFull(),
                                Forms\Components\Repeater::make('features_json')
                                    ->schema([
                                        Forms\Components\TextInput::make('text')->required(),
                                    ])
                                    ->columnSpanFull(),
                                Forms\Components\Repeater::make('combo_rules_json')
                                    ->schema([
                                        Forms\Components\TextInput::make('id')->required(),
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\TextInput::make('price')->numeric()->required(),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Tabs\Tab::make('SEO & Tracking')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title'),
                                Forms\Components\Textarea::make('seo_description'),
                                Forms\Components\TextInput::make('facebook_pixel_id'),
                                Forms\Components\TextInput::make('tiktok_pixel_id'),
                            ]),
                        Forms\Components\Tabs\Tab::make('Extras')
                            ->schema([
                                Forms\Components\DateTimePicker::make('urgency_end_time'),
                                Forms\Components\TextInput::make('urgency_fake_views')->numeric(),
                                Forms\Components\TextInput::make('header_logo_url')->url(),
                                Forms\Components\TextInput::make('header_cta_text'),
                                Forms\Components\Textarea::make('footer_content')->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('product.name')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListLandingPages::route('/'),
            'create' => Pages\CreateLandingPage::route('/create'),
            'edit' => Pages\EditLandingPage::route('/{record}/edit'),
        ];
    }
}
