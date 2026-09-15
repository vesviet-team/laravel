<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionRuleResource\Pages;
use App\Filament\Resources\PromotionRuleResource\RelationManagers;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PromotionRuleResource extends Resource
{
    protected static ?string $model = PromotionRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Chương Trình Khuyến Mãi';

    protected static ?string $modelLabel = 'Chương trình khuyến mãi';

    protected static ?string $pluralModelLabel = 'Chương trình khuyến mãi';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()->active()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Số lượng chương trình khuyến mãi đang có hiệu lực';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('PromotionRuleDetails')
                    ->tabs([
                        // Tab 1: Thông tin chung
                        Forms\Components\Tabs\Tab::make('Thông Tin Cơ Bản')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Tên chương trình khuyến mãi')
                                        ->placeholder('VD: Ưu Đãi Mùa Hè 2026 - Giảm 15%')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('code')
                                        ->label('Mã Coupon (Voucher Code)')
                                        ->placeholder('VD: WELCOME10 (Để trống nếu là khuyến mãi tự động)')
                                        ->maxLength(50)
                                        ->rules([
                                            fn (?PromotionRule $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                                if (! $value) {
                                                    return;
                                                }
                                                $cleanCode = strtoupper(trim($value));
                                                $query = PromotionRule::whereRaw('UPPER(code) = ?', [$cleanCode]);
                                                if ($record) {
                                                    $query->where('id', '!=', $record->id);
                                                }
                                                if ($query->exists()) {
                                                    $fail("Mã coupon '{$cleanCode}' đã tồn tại trong hệ thống.");
                                                }
                                            },
                                        ])
                                        ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(trim($state)) : null)
                                        ->suffixAction(
                                            Forms\Components\Actions\Action::make('generateCode')
                                                ->icon('heroicon-m-sparkles')
                                                ->tooltip('Tự động tạo mã ngẫu nhiên')
                                                ->action(fn (Set $set) => $set('code', strtoupper(Str::random(8))))
                                        )
                                        ->helperText('Nhập mã voucher viết hoa không dấu. Bỏ trống nếu muốn hệ thống tự động áp dụng khi giỏ hàng thỏa điều kiện.'),

                                    Forms\Components\TextInput::make('priority')
                                        ->label('Độ ưu tiên xử lý')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->required()
                                        ->helperText('Quy tắc có độ ưu tiên nhỏ hơn (0, 1, 2...) được tính toán và áp dụng trước.'),

                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Kích hoạt chương trình')
                                        ->default(true)
                                        ->helperText('Bật/Tắt hiệu lực của quy tắc khuyến mãi trên toàn bộ hệ thống.'),

                                    Forms\Components\Toggle::make('stop_further_rules')
                                        ->label('Dừng các quy tắc tiếp theo (Stop Further Rules)')
                                        ->default(false)
                                        ->helperText('Nếu quy tắc này được áp dụng thành công, hệ thống sẽ bỏ qua toàn bộ các quy tắc khuyến mãi tự động có độ ưu tiên thấp hơn.'),

                                    Forms\Components\Textarea::make('conditions.description')
                                        ->label('Mô tả chi tiết / Ghi chú nội bộ')
                                        ->placeholder('Ghi chú điều kiện áp dụng, mục tiêu chiến dịch hoặc tài liệu nội bộ...')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                            ]),

                        // Tab 2: Loại Quy Tắc & Mức Giảm Giá
                        Forms\Components\Tabs\Tab::make('Mức Giảm & Hình Thức')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('rule_type')
                                        ->label('Phạm vi áp dụng (Rule Type)')
                                        ->options([
                                            PromotionRule::RULE_TYPE_CART => 'Khuyến Mãi Giỏ Hàng & Coupon (Cart Sales Rule)',
                                            PromotionRule::RULE_TYPE_CATALOG => 'Khuyến Mãi Danh Mục & Giá Niêm Yết (Catalog Price Rule)',
                                        ])
                                        ->default(PromotionRule::RULE_TYPE_CART)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (string $state, Set $set, Get $get) {
                                            if ($state === PromotionRule::RULE_TYPE_CATALOG) {
                                                $set('code', null);
                                                $currentAction = $get('action_type');
                                                if (! in_array($currentAction, [PromotionRule::ACTION_PERCENTAGE, PromotionRule::ACTION_FIXED_AMOUNT], true)) {
                                                    $set('action_type', PromotionRule::ACTION_PERCENTAGE);
                                                }
                                            }
                                        })
                                        ->helperText('Catalog Rule tự động tính giá gạch và hiển thị badge -X% trên toàn bộ trang sản phẩm.'),

                                    Forms\Components\Select::make('action_type')
                                        ->label('Hình thức chiết khấu (Action Type)')
                                        ->options(fn (Get $get): array => match ($get('rule_type')) {
                                            PromotionRule::RULE_TYPE_CATALOG => [
                                                PromotionRule::ACTION_PERCENTAGE => 'Giảm theo phần trăm (%)',
                                                PromotionRule::ACTION_FIXED_AMOUNT => 'Giảm số tiền cố định (₫)',
                                            ],
                                            default => [
                                                PromotionRule::ACTION_PERCENTAGE => 'Giảm theo phần trăm (%)',
                                                PromotionRule::ACTION_FIXED_AMOUNT => 'Giảm số tiền cố định (₫)',
                                                PromotionRule::ACTION_BUY_X_GET_Y => 'Mua X Tặng Y (BXGY)',
                                                PromotionRule::ACTION_TIERED_QUANTITY => 'Chiết khấu bậc thang số lượng (Tiered)',
                                                PromotionRule::ACTION_FREE_SHIPPING => 'Miễn phí vận chuyển (Free Shipping)',
                                            ],
                                        })
                                        ->default(PromotionRule::ACTION_PERCENTAGE)
                                        ->required()
                                        ->live(),

                                    Forms\Components\TextInput::make('discount_value')
                                        ->label(fn (Get $get): string => match ($get('action_type')) {
                                            PromotionRule::ACTION_PERCENTAGE => 'Tỷ lệ giảm giá (%)',
                                            PromotionRule::ACTION_FIXED_AMOUNT => 'Số tiền giảm trực tiếp (₫)',
                                            PromotionRule::ACTION_FREE_SHIPPING => 'Mức hỗ trợ phí ship (₫, để 0 nếu miễn phí 100%)',
                                            PromotionRule::ACTION_TIERED_QUANTITY => 'Tỷ lệ giảm mặc định (%)',
                                            default => 'Giá trị chiết khấu',
                                        })
                                        ->numeric()
                                        ->required(fn (Get $get) => $get('action_type') !== PromotionRule::ACTION_BUY_X_GET_Y)
                                        ->default(0)
                                        ->prefix(fn (Get $get) => $get('action_type') === PromotionRule::ACTION_FIXED_AMOUNT ? '₫' : null)
                                        ->suffix(fn (Get $get) => in_array($get('action_type'), [PromotionRule::ACTION_PERCENTAGE, PromotionRule::ACTION_TIERED_QUANTITY]) ? '%' : null)
                                        ->visible(fn (Get $get) => $get('action_type') !== PromotionRule::ACTION_BUY_X_GET_Y)
                                        ->helperText('Nhập % chiết khấu (ví dụ: 10 cho 10%) hoặc số tiền VND cụ thể (ví dụ: 100000).'),

                                    Forms\Components\TextInput::make('max_discount_amount')
                                        ->label('Số tiền giảm tối đa (Trần giảm giá)')
                                        ->numeric()
                                        ->prefix('₫')
                                        ->nullable()
                                        ->visible(fn (Get $get) => in_array($get('action_type'), [
                                            PromotionRule::ACTION_PERCENTAGE,
                                            PromotionRule::ACTION_TIERED_QUANTITY,
                                            PromotionRule::ACTION_BUY_X_GET_Y,
                                        ]))
                                        ->helperText('Giới hạn mức tiền giảm cao nhất khi chiết khấu theo %, để trống nếu không giới hạn.'),
                                ]),
                            ]),

                        // Tab 3: Điều Kiện Áp Dụng & Hạn Mức
                        Forms\Components\Tabs\Tab::make('Hạn Mức & Phân Khúc')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('min_order_amount')
                                        ->label('Giá trị đơn hàng tối thiểu')
                                        ->numeric()
                                        ->default(0)
                                        ->prefix('₫')
                                        ->helperText('Tổng giá trị sản phẩm hợp lệ trong giỏ hàng tối thiểu để áp dụng.'),

                                    Forms\Components\TextInput::make('min_quantity')
                                        ->label('Số lượng sản phẩm tối thiểu')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('Số lượng sản phẩm tối thiểu trong giỏ hàng để kích hoạt.'),

                                    Forms\Components\Select::make('target_customer_tier')
                                        ->label('Phân khúc khách hàng áp dụng')
                                        ->options([
                                            PromotionRule::TIER_ALL => 'Tất cả khách hàng (Đại trà)',
                                            PromotionRule::TIER_FIRST_TIME => 'Khách hàng mới (Đơn hàng đầu tiên)',
                                            PromotionRule::TIER_BRONZE => 'Hạng Đồng (Tất cả thành viên đã đăng ký)',
                                            PromotionRule::TIER_SILVER => 'Hạng Bạc (Chi tiêu từ 5.000.000₫)',
                                            PromotionRule::TIER_GOLD => 'Hạng Vàng / VIP (Chi tiêu từ 20.000.000₫)',
                                            PromotionRule::TIER_PLATINUM => 'Hạng Bạch Kim (Chi tiêu từ 50.000.000₫)',
                                        ])
                                        ->default(PromotionRule::TIER_ALL)
                                        ->required(),

                                    Forms\Components\TextInput::make('usage_limit')
                                        ->label('Tổng lượt sử dụng toàn hệ thống')
                                        ->numeric()
                                        ->nullable()
                                        ->helperText('Để trống nếu không giới hạn số lượt áp dụng toàn sàn.'),

                                    Forms\Components\TextInput::make('usage_limit_per_user')
                                        ->label('Lượt sử dụng tối đa / 1 Khách hàng')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->required()
                                        ->helperText('Số lần tối đa mà 1 tài khoản hoặc 1 email có thể sử dụng quy tắc này.'),

                                    Forms\Components\TextInput::make('used_count')
                                        ->label('Số lượt đã sử dụng')
                                        ->numeric()
                                        ->default(0)
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->helperText('Bộ đếm tự động ghi nhận mỗi khi đơn hàng thanh toán thành công.'),
                                ]),
                            ]),

                        // Tab 4: Bộ Lọc Danh Mục, Sản Phẩm & BXGY
                        Forms\Components\Tabs\Tab::make('Cấu Hình Nâng Cao & Sản Phẩm')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Forms\Components\Section::make('Bộ Lọc Danh Mục & Sản Phẩm Hợp Lệ')
                                    ->description('Xác định các danh mục hoặc sản phẩm cụ thể áp dụng quy tắc khuyến mãi.')
                                    ->schema([
                                        Forms\Components\Select::make('conditions.category_ids')
                                            ->label('Danh mục sản phẩm áp dụng')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn () => Category::query()->pluck('name', 'id'))
                                            ->helperText('Để trống nếu áp dụng cho toàn bộ danh mục sản phẩm.'),

                                        Forms\Components\Select::make('conditions.product_ids')
                                            ->label('Sản phẩm cụ thể áp dụng')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn () => Product::query()->pluck('name', 'id'))
                                            ->helperText('Để trống nếu áp dụng cho tất cả sản phẩm trong danh mục.'),
                                    ]),

                                // Cấu hình BXGY (Mua X Tặng Y)
                                Forms\Components\Section::make('Cấu Hình Mua X Tặng Y (BXGY)')
                                    ->description('Thiết lập sản phẩm mua điều kiện và quà tặng / sản phẩm giảm giá kèm theo.')
                                    ->visible(fn (Get $get) => $get('action_type') === PromotionRule::ACTION_BUY_X_GET_Y)
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\Select::make('conditions.bxgy_config.buy_product_id')
                                                ->label('Sản phẩm mua (X)')
                                                ->options(fn () => Product::query()->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->required(fn (Get $get) => $get('action_type') === PromotionRule::ACTION_BUY_X_GET_Y),

                                            Forms\Components\TextInput::make('conditions.bxgy_config.buy_quantity')
                                                ->label('Số lượng mua (X)')
                                                ->numeric()
                                                ->default(1)
                                                ->minValue(1)
                                                ->required(fn (Get $get) => $get('action_type') === PromotionRule::ACTION_BUY_X_GET_Y),

                                            Forms\Components\Select::make('conditions.bxgy_config.get_product_id')
                                                ->label('Sản phẩm tặng / giảm giá (Y)')
                                                ->options(fn () => Product::query()->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->required(fn (Get $get) => $get('action_type') === PromotionRule::ACTION_BUY_X_GET_Y),

                                            Forms\Components\TextInput::make('conditions.bxgy_config.get_quantity')
                                                ->label('Số lượng tặng / giảm (Y)')
                                                ->numeric()
                                                ->default(1)
                                                ->minValue(1)
                                                ->required(fn (Get $get) => $get('action_type') === PromotionRule::ACTION_BUY_X_GET_Y),

                                            Forms\Components\Toggle::make('conditions.bxgy_config.is_free')
                                                ->label('Tặng miễn phí 100% (Free Gift)')
                                                ->default(true)
                                                ->live()
                                                ->helperText('Nếu bật, sản phẩm Y sẽ được giảm 100% giá trị.'),

                                            Forms\Components\TextInput::make('conditions.bxgy_config.discount_value')
                                                ->label('Mức giảm % cho sản phẩm Y')
                                                ->numeric()
                                                ->default(100)
                                                ->suffix('%')
                                                ->visible(fn (Get $get) => ! $get('conditions.bxgy_config.is_free')),

                                            Forms\Components\TextInput::make('conditions.bxgy_config.max_rewards')
                                                ->label('Số lượt tặng tối đa / 1 Đơn hàng')
                                                ->numeric()
                                                ->nullable()
                                                ->helperText('Giới hạn số phần quà tối đa mà khách nhận được trong 1 giỏ hàng (để trống nếu không giới hạn).'),
                                        ]),
                                    ]),

                                // Cấu hình Bậc Thang Số Lượng (Tiered Quantity)
                                Forms\Components\Section::make('Cấu Hình Bậc Thang Chiết Khấu Số Lượng')
                                    ->description('Thiết lập mức giảm giá lũy tiến theo số lượng sản phẩm mua trong giỏ hàng.')
                                    ->visible(fn (Get $get) => $get('action_type') === PromotionRule::ACTION_TIERED_QUANTITY)
                                    ->schema([
                                        Forms\Components\Repeater::make('conditions.tiered_steps')
                                            ->label('Các mức chiết khấu')
                                            ->schema([
                                                Forms\Components\TextInput::make('min_qty')
                                                    ->label('Số lượng tối thiểu (sp)')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->required(),

                                                Forms\Components\TextInput::make('discount_percent')
                                                    ->label('Mức giảm (%)')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(100)
                                                    ->suffix('%')
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(2)
                                            ->addActionLabel('Thêm bậc chiết khấu')
                                            ->helperText('Ví dụ: Mua từ 2 cái giảm 5%, từ 4 cái giảm 10%, từ 6 cái giảm 15%.'),
                                    ]),
                            ]),

                        // Tab 5: Lịch Trình Hiệu Lực
                        Forms\Components\Tabs\Tab::make('Thời Gian Hiệu Lực')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\DateTimePicker::make('starts_at')
                                        ->label('Thời gian bắt đầu')
                                        ->seconds(false)
                                        ->helperText('Để trống nếu có hiệu lực ngay lập tức.'),

                                    Forms\Components\DateTimePicker::make('ends_at')
                                        ->label('Thời gian kết thúc')
                                        ->seconds(false)
                                        ->helperText('Để trống nếu chương trình không giới hạn thời gian kết thúc.'),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên Chương Trình')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (PromotionRule $record): string => match ($record->rule_type) {
                        PromotionRule::RULE_TYPE_CATALOG => '🏷️ Catalog Price Rule (Giá niêm yết)',
                        default => $record->code ? "🎟️ Mã Coupon: {$record->code}" : '⚙️ Tự động giỏ hàng',
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Mã Coupon')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Đã sao chép mã coupon!')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'primary' : 'gray')
                    ->placeholder('— Tự động')
                    ->icon('heroicon-m-ticket'),

                Tables\Columns\TextColumn::make('rule_type')
                    ->label('Loại Quy Tắc')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PromotionRule::RULE_TYPE_CATALOG => 'info',
                        PromotionRule::RULE_TYPE_CART => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PromotionRule::RULE_TYPE_CATALOG => 'Catalog Rule',
                        PromotionRule::RULE_TYPE_CART => 'Cart Rule',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Hình Thức')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PromotionRule::ACTION_PERCENTAGE => 'warning',
                        PromotionRule::ACTION_FIXED_AMOUNT => 'success',
                        PromotionRule::ACTION_BUY_X_GET_Y => 'danger',
                        PromotionRule::ACTION_TIERED_QUANTITY => 'purple',
                        PromotionRule::ACTION_FREE_SHIPPING => 'cyan',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PromotionRule::ACTION_PERCENTAGE => 'Giảm %',
                        PromotionRule::ACTION_FIXED_AMOUNT => 'Giảm Tiền Mặt',
                        PromotionRule::ACTION_BUY_X_GET_Y => 'BXGY Tặng Quà',
                        PromotionRule::ACTION_TIERED_QUANTITY => 'Bậc Thang',
                        PromotionRule::ACTION_FREE_SHIPPING => 'Freeship',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Mức Giảm')
                    ->formatStateUsing(fn (PromotionRule $record): string => $record->formatted_discount)
                    ->description(fn (PromotionRule $record): ?string => $record->max_discount_amount
                        ? 'Tối đa: '.number_format($record->max_discount_amount, 0, ',', '.').'₫'
                        : null)
                    ->sortable(),

                Tables\Columns\TextColumn::make('usage_stat')
                    ->label('Đã Dùng / Hạn Mức')
                    ->formatStateUsing(fn (PromotionRule $record): string => "{$record->used_count} / ".($record->usage_limit !== null ? number_format($record->usage_limit, 0, ',', '.') : '∞'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('used_count', $direction)),

                Tables\Columns\TextColumn::make('schedule_status')
                    ->label('Thời Hạn')
                    ->badge()
                    ->color(function (PromotionRule $record): string {
                        $now = now();
                        if ($record->starts_at && $now->lt($record->starts_at)) {
                            return 'warning';
                        }
                        if ($record->ends_at && $now->gt($record->ends_at)) {
                            return 'danger';
                        }

                        return 'success';
                    })
                    ->formatStateUsing(function (PromotionRule $record): string {
                        $now = now();
                        if ($record->starts_at && $now->lt($record->starts_at)) {
                            return 'Sắp diễn ra ('.$record->starts_at->format('d/m/Y').')';
                        }
                        if ($record->ends_at && $now->gt($record->ends_at)) {
                            return 'Đã kết thúc ('.$record->ends_at->format('d/m/Y').')';
                        }
                        if ($record->ends_at) {
                            return 'Đến '.$record->ends_at->format('d/m/Y H:i');
                        }

                        return 'Vô thời hạn';
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Ưu Tiên')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Kích Hoạt')
                    ->sortable(),
            ])
            ->defaultSort('priority', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('rule_type')
                    ->label('Loại quy tắc')
                    ->options([
                        PromotionRule::RULE_TYPE_CART => 'Cart Rules (Giỏ hàng & Coupon)',
                        PromotionRule::RULE_TYPE_CATALOG => 'Catalog Rules (Danh mục & Giá niêm yết)',
                    ]),

                Tables\Filters\SelectFilter::make('action_type')
                    ->label('Hình thức chiết khấu')
                    ->options([
                        PromotionRule::ACTION_PERCENTAGE => 'Giảm theo phần trăm (%)',
                        PromotionRule::ACTION_FIXED_AMOUNT => 'Giảm số tiền cố định (₫)',
                        PromotionRule::ACTION_BUY_X_GET_Y => 'Mua X Tặng Y (BXGY)',
                        PromotionRule::ACTION_TIERED_QUANTITY => 'Chiết khấu bậc thang',
                        PromotionRule::ACTION_FREE_SHIPPING => 'Miễn phí vận chuyển',
                    ]),

                Tables\Filters\SelectFilter::make('target_customer_tier')
                    ->label('Phân khúc khách hàng')
                    ->options([
                        PromotionRule::TIER_ALL => 'Tất cả khách hàng',
                        PromotionRule::TIER_FIRST_TIME => 'Khách hàng mới',
                        PromotionRule::TIER_BRONZE => 'Hạng Đồng',
                        PromotionRule::TIER_SILVER => 'Hạng Bạc',
                        PromotionRule::TIER_GOLD => 'Hạng Vàng',
                        PromotionRule::TIER_PLATINUM => 'Hạng Bạch Kim',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái kích hoạt')
                    ->trueLabel('Đang kích hoạt')
                    ->falseLabel('Đang tạm dừng'),

                Tables\Filters\Filter::make('active_now')
                    ->label('Đang trong thời gian hiệu lực')
                    ->query(fn (Builder $query): Builder => $query->active()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-m-pencil-square'),

                Tables\Actions\ReplicateAction::make()
                    ->label('Nhân bản')
                    ->icon('heroicon-m-document-duplicate')
                    ->color('gray')
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['name'] = $data['name'].' (Bản sao)';
                        $data['code'] = ! empty($data['code']) ? $data['code'].'_COPY' : null;
                        $data['used_count'] = 0;
                        $data['is_active'] = false;

                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-m-trash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Kích hoạt các mục chọn')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Tạm dừng các mục chọn')
                        ->icon('heroicon-m-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PromotionUsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotionRules::route('/'),
            'create' => Pages\CreatePromotionRule::route('/create'),
            'edit' => Pages\EditPromotionRule::route('/{record}/edit'),
        ];
    }
}
