<?php

namespace App\Models;

use App\Models\Traits\BelongsToSeller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToSeller, HasFactory, SoftDeletes;

    protected static function booted()
    {
        parent::booted();
        static::saving(function ($product) {
            if ($product->status === 'active') {
                $product->status = 'published';
            }
        });
    }

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'image_path',
        'price',
        'compare_at_price',
        'stock',
        'low_stock_threshold',
        'weight',
        'length',
        'width',
        'height',
        'status',
        'is_featured',
        'is_visible',
        'is_purchasable',
        'published_at',
        'attributes_json',
        'options_json',
        'tags',
        'seller_id',
        'show_on_marketplace',
        'structured_data',
        'seo_title',
        'seo_description',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'attributes_json' => 'array',
        'tags' => 'array',
        'structured_data' => 'array',
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'weight' => 'integer',
        'length' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_featured' => 'boolean',
        'is_visible' => 'boolean',
        'is_purchasable' => 'boolean',
        'published_at' => 'datetime',
        'options_json' => 'array',
        'show_on_marketplace' => 'boolean',
    ];

    protected $appends = [
        'primary_image_url',
        'secondary_image_url',
        'gallery_images',
        'album_images',
        'thumbnail',
        'formatted_price',
        'formatted_compare_at_price',
        'is_in_stock',
        'is_low_stock',
        'stock_status_label',
        'stock_status_color',
        'discount_percentage',
        'has_discount',
        'dimensions',
        'volume_cm3',
        'schema_org_json_ld',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Blog posts referencing this product for contextual commerce.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_product')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Scope: Filter by category slug (or collection of category IDs).
     */
    public function scopeFilterByCategory($query, $categorySlug)
    {
        if (empty($categorySlug)) {
            return $query;
        }

        if (is_array($categorySlug)) {
            return $query->whereIn('category_id', $categorySlug);
        }

        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    /**
     * Scope: Search by keyword in name, sku, and description.
     */
    public function scopeSearch($query, ?string $keyword)
    {
        if (empty($keyword)) {
            return $query;
        }

        $term = trim($keyword);

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Scope: Filter by price range.
     */
    public function scopePriceRange($query, ?float $minPrice, ?float $maxPrice)
    {
        if (! is_null($minPrice) && $minPrice > 0) {
            $query->where('price', '>=', $minPrice);
        }

        if (! is_null($maxPrice) && $maxPrice > 0) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query;
    }

    /**
     * Scope: Filter only in-stock items.
     */
    public function scopeInStock($query, bool $onlyInStock = true)
    {
        if ($onlyInStock) {
            return $query->where('stock', '>', 0);
        }

        return $query;
    }

    /**
     * Scope: Filter by low stock.
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock > 0 AND stock <= low_stock_threshold');
    }

    /**
     * Scope: Filter by tags (flexible tagging system).
     */
    public function scopeWithTags($query, array $tags)
    {
        if (empty($tags)) {
            return $query;
        }

        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereJsonContains('tags', $tag);
            }
        });
    }

    /**
     * Scope: Filter by attribute values.
     */
    public function scopeWithAttributes($query, array $attributes)
    {
        if (empty($attributes)) {
            return $query;
        }

        return $query->where(function ($q) use ($attributes) {
            foreach ($attributes as $key => $value) {
                $q->orWhereJsonContains("attributes_json->{$key}", $value);
            }
        });
    }

    /**
     * Scope: Sort by predefined sorting criteria.
     */
    public function scopeSortedBy($query, ?string $sort)
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'featured' => $query->orderBy('is_featured', 'desc')->latest(),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            'best_selling' => $query->orderBy('stock', 'asc'), // Placeholder for actual sales count
            'top_rated' => $query->whereHas('reviews', fn ($q) => $q->where('status', 'approved'))
                ->withAvg('reviews as avg_rating', 'rating')
                ->orderBy('avg_rating', 'desc'),
            'on_sale' => $query->whereNotNull('compare_at_price')
                ->whereRaw('compare_at_price > price'),
            default => $query->latest(),
        };
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'published'])
            ->where('is_visible', true);
    }

    /**
     * Scope: featured AND active products for homepage display.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)
            ->whereIn('status', ['active', 'published'])
            ->where('is_visible', true);
    }

    /**
     * Scope: New arrivals (published in last 30 days).
     */
    public function scopeNewArrivals($query, int $days = 30)
    {
        return $query->where('published_at', '>=', now()->subDays($days))
            ->whereIn('status', ['active', 'published'])
            ->where('is_visible', true);
    }

    /**
     * Scope: On sale products.
     */
    public function scopeOnSale($query)
    {
        return $query->whereNotNull('compare_at_price')
            ->whereRaw('compare_at_price > price')
            ->whereIn('status', ['active', 'published'])
            ->where('is_visible', true);
    }

    /**
     * Scope: Published products (for public catalog).
     */
    public function scopePublished($query)
    {
        return $query->whereIn('status', ['active', 'published'])
            ->where('is_visible', true)
            ->where(fn ($q) => $q
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    /**
     * Resolve any image path or URL safely.
     */
    public static function resolveImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        return Storage::url($path);
    }

    /**
     * Primary image URL accessor.
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        if (! empty($this->image_path)) {
            return static::resolveImageUrl($this->image_path);
        }

        if (! empty($this->attributes_json['primary_image'])) {
            return static::resolveImageUrl($this->attributes_json['primary_image']);
        }

        return null;
    }

    /**
     * Secondary / hover image URL accessor.
     */
    public function getSecondaryImageUrlAttribute(): ?string
    {
        $secondary = $this->secondary_image_path
            ?? $this->secondary_image
            ?? $this->hover_image
            ?? ($this->attributes_json['secondary_image'] ?? null);

        if (! empty($secondary)) {
            return static::resolveImageUrl($secondary);
        }

        return null;
    }

    /**
     * Complete gallery images list accessor.
     */
    public function getGalleryImagesAttribute(): array
    {
        $images = [];

        if ($primary = $this->primary_image_url) {
            $images[] = $primary;
        }

        if ($secondary = $this->secondary_image_url) {
            if (! in_array($secondary, $images)) {
                $images[] = $secondary;
            }
        }

        if (! empty($this->attributes_json['gallery']) && is_array($this->attributes_json['gallery'])) {
            foreach ($this->attributes_json['gallery'] as $item) {
                if ($url = static::resolveImageUrl($item)) {
                    if (! in_array($url, $images)) {
                        $images[] = $url;
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Lifestyle lookbook / gallery album items.
     * Returns an array of structured items with 'url', 'title', 'tag', 'caption'
     */
    public function getAlbumImagesAttribute(): array
    {
        if (! empty($this->attributes_json['album']) && is_array($this->attributes_json['album'])) {
            $album = [];
            foreach ($this->attributes_json['album'] as $item) {
                if (is_string($item)) {
                    if ($url = static::resolveImageUrl($item)) {
                        $album[] = [
                            'url' => $url,
                            'title' => $this->name,
                            'tag' => 'KhÃ´ng Gian Sá»‘ng',
                            'caption' => 'Phong cÃ¡ch bÃ i trÃ­ ná»™i tháº¥t Scandinavian hiá»‡n Ä‘áº¡i',
                        ];
                    }
                } elseif (is_array($item) && ! empty($item['url'])) {
                    $item['url'] = static::resolveImageUrl($item['url']);
                    $album[] = $item;
                }
            }
            if (! empty($album)) {
                return $album;
            }
        }

        // Fallback structured lookbook album from gallery images
        $gallery = $this->gallery_images;
        $album = [];
        $tags = ['GÃ³c Studio SÃ¡ng Táº¡o', 'BÃ n Cáº¡nh GiÆ°á»ng & ÄÃ´n TrÆ°ng BÃ y', 'KhÃ´ng Gian PhÃ²ng KhÃ¡ch', 'Nghá»‡ Thuáº­t Xáº¿p Chá»“ng ÄiÃªu Kháº¯c'];
        foreach ($gallery as $index => $imgUrl) {
            $album[] = [
                'url' => $imgUrl,
                'title' => $this->name.' â€” GÃ³c NhÃ¬n '.($index + 1),
                'tag' => $tags[$index % count($tags)],
                'caption' => 'Thiáº¿t káº¿ tá»‘i giáº£n káº¿t há»£p hoÃ n háº£o trong khÃ´ng gian sá»‘ng hiá»‡n Ä‘áº¡i.',
            ];
        }

        return $album;
    }

    /**
     * Thumbnail accessor â€” maps ->thumbnail to primary_image_url.
     * CartService and views reference ->thumbnail.
     */
    public function getThumbnailAttribute(): ?string
    {
        return $this->primary_image_url;
    }

    /**
     * Formatted price in Vietnamese Dong (VND).
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', '.').'â‚«';
    }

    /**
     * Formatted compare-at price in Vietnamese Dong (VND).
     */
    public function getFormattedCompareAtPriceAttribute(): ?string
    {
        if ($this->compare_at_price) {
            return number_format($this->compare_at_price, 0, ',', '.').'â‚«';
        }

        return null;
    }

    /**
     * Check if product has a discount/sale price.
     */
    public function getHasDiscountAttribute(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    /**
     * Discount percentage.
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (! $this->has_discount || ! $this->compare_at_price) {
            return null;
        }

        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }

    /**
     * Check if product is in stock.
     */
    public function getIsInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Check if product is low stock.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->low_stock_threshold;
    }

    /**
     * Stock status text label.
     */
    public function getStockStatusLabelAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'Hết hàng';
        }

        if ($this->is_low_stock) {
            return "Sắp hết hàng (còn {$this->stock})";
        }

        return "Còn hàng ({$this->stock})";
    }

    /**
     * Stock status Tailwind badge color class.
     */
    public function getStockStatusColorAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'text-[#E84444]';
        }

        if ($this->is_low_stock) {
            return 'text-amber-600';
        }

        return 'text-emerald-600';
    }

    /**
     * Product dimensions as formatted string.
     */
    public function getDimensionsAttribute(): ?string
    {
        if ($this->length && $this->width && $this->height) {
            return "{$this->length} x {$this->width} x {$this->height} cm";
        }

        return null;
    }

    /**
     * Volume in cubic cm.
     */
    public function getVolumeCm3Attribute(): ?int
    {
        if ($this->length && $this->width && $this->height) {
            return $this->length * $this->width * $this->height;
        }

        return null;
    }

    /**
     * Get related products from the same category.
     */
    public function getRelatedProducts(int $limit = 4)
    {
        return static::active()
            ->where('id', '!=', $this->id)
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->with(['category'])
            ->take($limit)
            ->get();
    }

    /**
     * Export standard Schema.org Product JSON-LD array.
     */
    public function toSchemaOrgJsonLd(string $url): array
    {
        $gallery = $this->gallery_images;
        if (empty($gallery) && $this->primary_image_url) {
            $gallery = [$this->primary_image_url];
        }

        $offers = [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => 'VND',
            'price' => (string) $this->price,
            'availability' => 'https://schema.org/'.($this->stock > 0 ? 'InStock' : 'OutOfStock'),
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        // Add sale price if applicable
        if ($this->has_discount) {
            $offers['price'] = (string) $this->price;
            $offers['priceValidUntil'] = now()->addDays(30)->format('Y-m-d');
        }

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $this->name,
            'description' => $this->seo_description ?? strip_tags($this->description ?? $this->name),
            'sku' => $this->sku ?? "PRD-{$this->id}",
            'offers' => $offers,
        ];

        if (! empty($gallery)) {
            $schema['image'] = $gallery;
        }

        if ($this->category) {
            $schema['category'] = $this->category->name;
        }

        if ($this->brand_name ?? $this->attributes_json['brand'] ?? null) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $this->attributes_json['brand'] ?? 'Sober Furniture',
            ];
        }

        // Add aggregate rating if reviews exist
        if ($this->reviews()->where('status', 'approved')->exists()) {
            $avgRating = $this->reviews()->where('status', 'approved')->avg('rating');
            $reviewCount = $this->reviews()->where('status', 'approved')->count();
            if ($avgRating) {
                $schema['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round($avgRating, 1),
                    'reviewCount' => $reviewCount,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ];
            }
        }

        return $schema;
    }

    public function getSchemaOrgJsonLdAttribute(): array
    {
        return $this->toSchemaOrgJsonLd(request()->url());
    }
}
