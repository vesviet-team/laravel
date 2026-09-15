<?php

namespace App\Actions;

use App\Exceptions\SellerActionException;
use App\Models\SellerPage;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * AdminUpdateSellerSlugAction — allows Admin to rename a seller's shop_slug.
 *
 * Slice 3 / ADR-SC1: shop_slug is ADMIN-ONLY. Sellers cannot rename it.
 * This Action is intentionally separate from UpdateSellerProfileAction (which
 * is scoped to seller-editable fields and used in the Seller panel).
 *
 * Responsibilities:
 *   - Validate slug format: [a-z0-9-]+ (consistent with route constraint)
 *   - Check uniqueness (excluding current seller)
 *   - Update in a DB::transaction (ADR-S2)
 *   - Invalidate storefront:page:{seller_id} cache
 *
 * ADR-S2: This Action owns the DB::transaction() boundary.
 *
 * @throws SellerActionException
 */
class AdminUpdateSellerSlugAction
{
    /** Allowed slug format: lowercase alphanumeric + hyphens, 3-63 chars (RFC-compliant). */
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$|^[a-z0-9]{1,63}$/';

    /**
     * Update the shop_slug for a seller and invalidate their storefront cache.
     *
     * @param  SellerProfile  $seller  The seller whose slug will be renamed.
     * @param  string  $newSlug  The new slug value (must be [a-z0-9-]+).
     *
     * @throws SellerActionException
     */
    public function execute(SellerProfile $seller, string $newSlug): SellerProfile
    {
        // Validate format
        if (! Str::of($newSlug)->test(self::SLUG_PATTERN)) {
            throw SellerActionException::invalidShopSlugFormat($newSlug);
        }

        // Check reserved subdomains (same list as SellerProfile::RESERVED_SUBDOMAINS)
        if (in_array($newSlug, SellerProfile::RESERVED_SUBDOMAINS, true)) {
            throw SellerActionException::invalidShopSlugFormat($newSlug);
        }

        // Uniqueness check (app-level pre-check before DB — DB UNIQUE is the authority)
        if (SellerProfile::where('shop_slug', $newSlug)
            ->where('id', '!=', $seller->id)
            ->exists()
        ) {
            throw SellerActionException::shopSlugCollision($newSlug);
        }

        try {
            return DB::transaction(function () use ($seller, $newSlug) {
                $seller->shop_slug = $newSlug;
                $seller->save();

                return $seller->refresh();
            });
        } catch (SellerActionException $e) {
            throw $e;
        } catch (Throwable $e) {
            // DB UNIQUE constraint violation surfaces here on race condition
            throw SellerActionException::shopSlugCollision($newSlug);
        } finally {
            // Invalidate storefront cache — seller_id key is stable across slug renames.
            // Runs even on rollback to prevent stale state.
            Cache::forget(SellerPage::cacheKeyFor($seller->id));
        }
    }
}
