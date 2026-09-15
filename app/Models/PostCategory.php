<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
        'seo_title',
        'seo_description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * All posts belonging to this category.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_category_id');
    }

    /**
     * Only published posts belonging to this category.
     */
    public function publishedPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_category_id')->published();
    }

    /**
     * Scope query to only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to order by sort_order ascending then name ascending.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Scope query with count of published articles.
     */
    public function scopeWithPublishedPostsCount($query)
    {
        return $query->withCount(['posts' => fn ($q) => $q->published()]);
    }
}
