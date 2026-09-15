<?php

namespace App\Models;

use App\Services\ShortcodeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_category_id',
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'content',
        'featured_image',
        'banner_image',
        'og_image',
        'status',
        'published_at',
        'is_featured',
        'reading_time_minutes',
        'seo_title',
        'seo_description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'schema_type',
        'faq_schema',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'reading_time_minutes' => 'integer',
            'faq_schema' => 'array',
        ];
    }

    /**
     * Model boot lifecycle events.
     */
    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            $rawContent = $post->body ?? $post->content ?? $post->attributes['body'] ?? $post->attributes['content'] ?? '';
            $post->reading_time_minutes = static::calculateReadingTime($rawContent);

            if ($post->status === 'published' && is_null($post->published_at)) {
                $post->published_at = Carbon::now();
            }
        });
    }

    /**
     * Calculate reading time in minutes based on multi-byte word count (~200 wpm).
     */
    public static function calculateReadingTime(?string $content): int
    {
        if (empty($content)) {
            return 1;
        }

        // 1. Strip script and style blocks along with their inner contents
        $cleanText = preg_replace('/<(script|style)\b[^>]*>.*?<\/\s*\1>/si', ' ', $content) ?? $content;

        // 2. Strip HTML comments
        $cleanText = preg_replace('/<!--.*?-->/s', ' ', $cleanText) ?? $cleanText;

        // 3. Replace remaining HTML tags with whitespace to preserve word boundaries
        $cleanText = preg_replace('/<[^>]+>/u', ' ', $cleanText) ?? $cleanText;

        // 4. Decode HTML entities (&nbsp;, &amp;, &quot;, etc.)
        $cleanText = html_entity_decode($cleanText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 5. Normalize Unicode and whitespace sequences
        $cleanText = preg_replace('/[\s\p{Z}\p{C}]+/u', ' ', trim($cleanText)) ?? trim($cleanText);

        // 6. Split into words using UTF-8 regex
        $words = preg_split('/\s+/u', $cleanText, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = is_array($words) ? count($words) : 0;

        return max(1, (int) ceil($wordCount / 200));
    }

    /**
     * Content accessor alias for body.
     */
    public function getContentAttribute(): ?string
    {
        return $this->attributes['body'] ?? $this->attributes['content'] ?? null;
    }

    /**
     * Content mutator alias for body.
     */
    public function setContentAttribute($value): void
    {
        $this->attributes['body'] = $value;
    }

    /**
     * Relationship: PostCategory (primary).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /**
     * Relationship: PostCategory (alias).
     */
    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /**
     * Relationship: Author (User).
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: User (alias).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: Attached Products for contextual commerce.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'post_product')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order', 'asc');
    }

    /**
     * Scope: Only published posts with past or current published_at.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', Carbon::now());
    }

    /**
     * Scope: Published AND featured posts.
     */
    public function scopeFeatured($query)
    {
        return $query->published()->where('is_featured', true);
    }

    /**
     * Scope: Filter by category (by ID, slug, or PostCategory model).
     */
    public function scopeByCategory($query, $category)
    {
        if (is_numeric($category)) {
            return $query->where('post_category_id', (int) $category);
        }

        if (is_string($category)) {
            return $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($category instanceof PostCategory) {
            return $query->where('post_category_id', $category->id);
        }

        return $query;
    }

    /**
     * Accessor: Featured image resolved URL.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        return Product::resolveImageUrl($this->featured_image);
    }

    /**
     * Accessor: Banner / OG image resolved URL.
     */
    public function getBannerImageUrlAttribute(): ?string
    {
        return Product::resolveImageUrl($this->og_image ?? $this->banner_image);
    }

    /**
     * Accessor: OG Image resolved URL.
     */
    public function getOgImageUrlAttribute(): ?string
    {
        return Product::resolveImageUrl($this->og_image ?? $this->banner_image ?? $this->featured_image);
    }

    /**
     * Scope: Search posts by keyword across title, excerpt, and body.
     */
    public function scopeSearch($query, ?string $keyword)
    {
        if (empty($keyword)) {
            return $query;
        }

        $term = trim($keyword);

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%");
        });
    }

    /**
     * Formatted published date string in Vietnamese format (d/m/Y).
     */
    public function getFormattedPublishedDateAttribute(): string
    {
        return $this->published_at ? $this->published_at->format('d/m/Y') : $this->created_at->format('d/m/Y');
    }

    /**
     * Get related articles from the same category excluding self.
     */
    public function getRelatedPosts(int $limit = 3)
    {
        if (! $this->post_category_id) {
            return collect();
        }

        return static::published()
            ->where('post_category_id', $this->post_category_id)
            ->where('id', '!=', $this->id)
            ->with('category')
            ->latest('published_at')
            ->take($limit)
            ->get();
    }

    /**
     * Generate Schema.org JSON-LD Article / BlogPosting representation.
     */
    public function toSchemaOrgJsonLd(string $url): array
    {
        $shortcodeService = app(ShortcodeService::class);
        $plainBody = $shortcodeService->strip($this->body);
        $metaDescription = $this->seo_description ?: ($this->excerpt ?: Str::limit(strip_tags($plainBody), 160));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $this->schema_type ?: 'BlogPosting',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
            'headline' => $this->title,
            'description' => $metaDescription,
            'image' => [
                $this->featured_image_url ?: asset('images/default-og.jpg'),
            ],
            'datePublished' => $this->published_at?->toIso8601String(),
            'dateModified' => $this->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $this->author?->name ?: config('app.name', 'Sober Editorial'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'Sober Furniture'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/logo.png'),
                ],
            ],
        ];

        return $schema;
    }
}
