<?php

namespace App\Actions;

use App\Exceptions\SellerActionException;
use App\Models\SellerPage;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Update a SellerProfile's shop information.
 *
 * SF-05 fix: Previously, Filament's EditTenantProfile saved SellerProfile directly via
 * Eloquent, with no validation gate and no cache invalidation. This Action provides:
 *   - A transaction boundary (ADR-S2)
 *   - Cache invalidation for the storefront page cache
 *   - Safe data filtering — only allowed fields are updated
 *
 * ADR-S2: This Action owns the sole DB::transaction() boundary.
 *
 * @throws SellerActionException
 */
class UpdateSellerProfileAction
{
    /**
     * Allowed fillable fields that a seller can update via the profile form.
     * Subdomain is intentionally excluded — it is immutable after creation.
     */
    private const ALLOWED_FIELDS = [
        'shop_name',
        'phone',
        'email',
        'bio',
        'bank_code',
        'bank_account_no',
        'bank_account_name',
        'shipping_type',
        'shipping_fee',
        'logo_url',
        'telegram_chat_id',
    ];

    /**
     * Update the seller profile with validated data and invalidate cache.
     *
     * @param  array  $data  Validated form data from EditSellerProfile.
     *
     * @throws SellerActionException
     */
    public function execute(SellerProfile $seller, array $data): SellerProfile
    {
        // Slice 0 / P0-B: Capture seller_id (stable) before update — not subdomain (immutable
        // but string-typed; using int id is consistent with ADR-SC1 cache key contract).
        $sellerId = $seller->id;

        try {
            return DB::transaction(function () use ($seller, $data) {
                // Filter to only allowed fields — subdomain cannot be changed.
                $safeData = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));

                $seller->fill($safeData);
                $seller->save();

                return $seller->refresh();
            });
        } catch (Throwable $e) {
            throw new SellerActionException(
                'Không thể cập nhật thông tin cửa hàng: '.$e->getMessage(),
                'seller_profile_update_failed',
                $e,
            );
        } finally {
            // Invalidate storefront cache — runs even on rollback to prevent stale state.
            Cache::forget(SellerPage::cacheKeyFor($sellerId));
        }
    }
}
