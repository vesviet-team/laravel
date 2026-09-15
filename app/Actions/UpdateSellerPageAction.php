<?php

namespace App\Actions;

use App\Exceptions\SellerActionException;
use App\Models\SellerPage;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Update a Seller's page configuration and invalidate the storefront cache.
 *
 * SF-04 fix: Previously, Filament's EditRecord saved SellerPage directly via Eloquent.
 * This bypassed the cache invalidation in PublishSellerPageAction. Now, all page saves
 * route through this Action which guarantees cache is always cleared on any update.
 *
 * ADR-S2: This Action owns the sole DB::transaction() boundary.
 * Cache invalidation runs in finally{} — fires even on rollback to prevent stale cache.
 *
 * @throws SellerActionException
 */
class UpdateSellerPageAction
{
    /**
     * Update the seller page with new data and invalidate storefront cache.
     *
     * @param  SellerProfile  $seller  The active seller tenant.
     * @param  array  $data  Validated data from EditSellerPage form.
     *
     * @throws SellerActionException
     */
    public function execute(SellerProfile $seller, array $data): SellerPage
    {
        // Slice 0 / P0-B: cache key uses seller_id (stable) — not subdomain (mutable).
        $cacheKey = SellerPage::cacheKeyFor($seller->id);

        try {
            $page = DB::transaction(function () use ($seller, $data) {
                $page = SellerPage::where('seller_id', $seller->id)->firstOrFail();

                $page->fill(array_filter([
                    'theme_config' => $data['theme_config'] ?? null,
                    'blocks' => $data['blocks'] ?? null,
                    'is_published' => $data['is_published'] ?? null,
                ], fn ($v) => $v !== null));

                $page->save();

                return $page;
            });

            return $page;
        } catch (SellerActionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw SellerActionException::pageUpdateFailed($e);
        } finally {
            // Cache invalidation runs regardless of tx outcome.
            // Prevents stale cache from being served after a partial failure.
            Cache::forget($cacheKey);
        }
    }
}
