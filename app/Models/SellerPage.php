<?php

namespace App\Models;

use App\Models\Traits\BelongsToSeller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellerPage extends Model
{
    // Slice 0 / P0-A: UsesTenantConnection removed — this project uses shared-database
    // multi-tenancy. BelongsToSeller global scope (TenantSellerScope) handles seller_id
    // filtering. UsesTenantConnection would switch DB connections which don't exist here.
    use BelongsToSeller, HasFactory, SoftDeletes;

    /**
     * Cache key prefix for storefront page cache.
     *
     * Slice 0 / P0-B: Key uses seller_id (stable integer) instead of subdomain/shop_slug
     * (mutable strings). This prevents ghost cache entries when Admin renames shop_slug.
     * Format: 'storefront:page:{seller_id}'
     */
    public const CACHE_KEY_PREFIX = 'storefront:page:';

    protected $fillable = [
        'seller_id',
        'theme_config',
        'blocks',
        'is_published',
    ];

    protected $casts = [
        'theme_config' => 'array',
        'blocks' => 'array',
        'is_published' => 'boolean',
    ];

    // seller() relationship is provided by BelongsToSeller trait

    /**
     * Return the cache key for this seller's storefront page.
     *
     * Uses seller_id (stable) — not subdomain/shop_slug (mutable).
     * All callers must pass $seller->id, never a string slug.
     *
     * ADR-SC1: cache key stability requirement.
     */
    public static function cacheKeyFor(int $sellerId): string
    {
        return self::CACHE_KEY_PREFIX.$sellerId;
    }
}
