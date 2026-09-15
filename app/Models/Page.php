<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'content',
        'featured_image',
        'og_image',
        'is_published',
        'published_at',
        'template',
        'seo_title',
        'seo_description',
        'meta_title',
        'meta_description',
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
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'faq_schema' => 'array',
        ];
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
     * Scope: Published pages (is_published = true, and published_at is null or in the past).
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', Carbon::now());
            });
    }

    /**
     * Accessor: Featured image resolved URL.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        return Product::resolveImageUrl($this->featured_image);
    }

    /**
     * Accessor: OG Image resolved URL.
     */
    public function getOgImageUrlAttribute(): ?string
    {
        return Product::resolveImageUrl($this->og_image ?? $this->featured_image);
    }

    /**
     * SEO title accessor fallback to meta_title or title.
     */
    public function getSeoTitleAttribute(): ?string
    {
        return $this->attributes['seo_title'] ?? $this->attributes['meta_title'] ?? null;
    }

    /**
     * Scope: Search pages by keyword across title, excerpt, and body.
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
     * Generate Schema.org JSON-LD WebPage representation.
     */
    public function toSchemaOrgJsonLd(string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $this->schema_type ?: 'WebPage',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
            'name' => $this->title,
            'description' => $this->seo_description ?: ($this->excerpt ?: Str::limit(strip_tags($this->body), 160)),
            'datePublished' => $this->published_at?->toIso8601String(),
            'dateModified' => $this->updated_at->toIso8601String(),
        ];
    }
}
