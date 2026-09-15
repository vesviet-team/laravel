<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
        'image_path',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'level',
        'path',
        'is_visible',
        'structured_data',
    ];

    protected $casts = [
        'structured_data' => 'array',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'level' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'full_path',
        'active_products_count',
        'schema_org_json_ld',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts()
    {
        return $this->hasMany(Product::class)
            ->whereIn('status', ['active', 'published'])
            ->where('is_visible', true);
    }

    public function scopeWithActiveProductsCount($query)
    {
        return $query->withCount(['products' => fn ($q) => $q->active()]);
    }

    public function scopeParentsOnly($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function scopeNested($query)
    {
        return $query->with('children')
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Recursively retrieve all descendant category IDs including self.
     */
    public function getAllChildrenIds(): array
    {
        $ids = [$this->id];

        $children = $this->relationLoaded('children') ? $this->children : $this->children()->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }

        return array_unique($ids);
    }

    /**
     * Get all ancestor categories up to root.
     */
    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get ancestor IDs including self.
     */
    public function getAncestorIds(): array
    {
        return $this->getAncestors()->pluck('id')->prepend($this->id)->toArray();
    }

    /**
     * Get breadcrumb trail (ancestors + self).
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];

        foreach ($this->getAncestors() as $ancestor) {
            $breadcrumbs[] = [
                'id' => $ancestor->id,
                'name' => $ancestor->name,
                'slug' => $ancestor->slug,
                'url' => route('products.index', ['category' => $ancestor->slug]),
            ];
        }

        $breadcrumbs[] = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => route('products.index', ['category' => $this->slug]),
        ];

        return $breadcrumbs;
    }

    /**
     * Get full path string (e.g., "Furniture > Chairs > Dining Chairs").
     */
    public function getFullPathAttribute(): string
    {
        return $this->getAncestors()->pluck('name')->push($this->name)->implode(' > ');
    }

    /**
     * Resolve category image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') ||
            str_starts_with($this->image_path, 'https://') ||
            str_starts_with($this->image_path, '//')) {
            return $this->image_path;
        }

        return Storage::url($this->image_path);
    }

    /**
     * Get active products count (cached via withCount).
     */
    public function getActiveProductsCountAttribute(): int
    {
        return $this->active_products_count ?? $this->activeProducts()->count();
    }

    /**
     * Check if category has children.
     */
    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if category is a root category.
     */
    public function getIsRootAttribute(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Get category level depth.
     */
    public function getDepthAttribute(): int
    {
        return $this->level ?? $this->getAncestors()->count();
    }

    /**
     * Export Schema.org Category JSON-LD.
     */
    public function toSchemaOrgJsonLd(string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Category',
            'name' => $this->name,
            'description' => $this->meta_description ?? $this->description ?? $this->name,
            'url' => $url,
        ];

        if ($this->image_url) {
            $schema['image'] = $this->image_url;
        }

        if ($this->parent) {
            $schema['parentCategory'] = [
                '@type' => 'Category',
                'name' => $this->parent->name,
            ];
        }

        return $schema;
    }

    public function getSchemaOrgJsonLdAttribute(): array
    {
        return $this->toSchemaOrgJsonLd(route('products.index', ['category' => $this->slug]));
    }

    /**
     * Build category tree for navigation.
     */
    public static function getNavigationTree(): \Illuminate\Database\Eloquent\Collection
    {
        return static::root()
            ->with(['children' => function ($query) {
                $query->where('is_visible', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->with(['children' => function ($q) {
                        $q->where('is_visible', true)
                            ->orderBy('sort_order')
                            ->orderBy('name');
                    }]);
            }])
            ->get();
    }

    /**
     * Get flat list of all categories with indentation for select inputs.
     */
    public static function getFlatListWithIndentation(): array
    {
        $categories = static::ordered()->get();
        $list = [];

        foreach ($categories as $category) {
            $indent = str_repeat('— ', $category->level);
            $list[$category->id] = $indent.$category->name;
        }

        return $list;
    }
}
