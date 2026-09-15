<?php

namespace App\Models;

use App\Observers\BannerObserver;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([BannerObserver::class])]
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    public const POSITION_HERO_SLIDER = 'hero_slider';

    public const POSITION_HOME_PROMO_2COL = 'home_promo_2col';

    public const POSITION_HOME_COLLECTION_3COL = 'home_collection_3col';

    public const POSITION_CATALOG_HEADER = 'catalog_header';

    public const POSITION_BLOG_SIDEBAR = 'blog_sidebar';

    public const POSITION_TOP_ANNOUNCEMENT = 'top_announcement';

    public const POSITIONS = [
        self::POSITION_HERO_SLIDER => '🌟 Slide Trang Chủ (Hero)',
        self::POSITION_HOME_PROMO_2COL => '🏷️ Khuyến Mãi 2 Cột (Promo)',
        self::POSITION_HOME_COLLECTION_3COL => '🛋️ Bộ Sưu Tập 3 Cột (Collection)',
        self::POSITION_CATALOG_HEADER => '📦 Header Catalog',
        self::POSITION_BLOG_SIDEBAR => '📰 Blog Sidebar',
        self::POSITION_TOP_ANNOUNCEMENT => '📢 Thông Báo Header',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'position',
        'eyebrow',
        'subtitle',
        'image',
        'link',
        'cta_text',
        'open_in_new_tab',
        'status',
        'starts_at',
        'ends_at',
        'sort_order',
        'clicks_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
            'clicks_count' => 'integer',
        ];
    }

    /**
     * Scope: Filter active banners within valid scheduling window.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('status', 'active')
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Scope: Filter banners by position identifier.
     */
    public function scopePosition(Builder $query, string $pos): Builder
    {
        return $query->where('position', $pos);
    }

    /**
     * Scope: Order banners by sort_order ascending.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Record a click event atomically without firing model events (protects cache invalidation).
     */
    public function recordClick(): bool
    {
        return (bool) static::withoutEvents(function () {
            return $this->increment('clicks_count');
        });
    }

    /**
     * Accessor: Resolve full image URL for local disk paths or external URLs.
     */
    public function getImageUrlAttribute(): string
    {
        if (empty($this->image)) {
            return '';
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL) || str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }
}
