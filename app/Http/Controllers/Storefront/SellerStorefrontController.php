<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\SellerPage;
use App\Models\SellerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Models\Tenant;

/**
 * SellerStorefrontController — serves the public-facing seller storefront pages.
 *
 * SF-08 fix: The index() action caches the published SellerPage for 10 minutes.
 *
 * Slice 2 / ADR-SC1: Dual-mode routing:
 *   - shopPath()         → /shop/{shop_slug} — canonical, works all environments
 *   - subdomainRedirect() → {subdomain}.domain/ — secondary, production only, 301 → /shop/
 *
 * Cache key: storefront:page:{seller_id} (stable integer — see ADR-SC1).
 * Cache is invalidated by UpdateSellerPageAction, PublishSellerPageAction,
 * AdminUpdateSellerSlugAction on every save.
 *
 * The preview() action intentionally bypasses cache — always shows the latest draft.
 *
 * Tenant context for /shop/ route:
 *   shopPath() calls $seller->makeCurrent() so QuickCheckout Livewire (which uses
 *   Tenant::current()) works correctly. EnsureSellerTenantForgotten middleware
 *   calls forgetCurrent() in terminate() to prevent cross-request leakage.
 */
class SellerStorefrontController extends Controller
{
    /**
     * Cache TTL for the public storefront (seconds).
     * Matches typical seller page update frequency.
     */
    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Display the Seller Storefront via path-based routing (/shop/{shop_slug}).
     *
     * ADR-SC1 canonical route. Works in all environments (no wildcard DNS required).
     * Sets Spatie tenant context via makeCurrent() for QuickCheckout Livewire compatibility.
     * Cache key uses seller_id (stable) — not shop_slug (mutable).
     */
    public function shopPath(Request $request, string $shop_slug): mixed
    {
        $seller = SellerProfile::where('shop_slug', $shop_slug)
            ->where('status', 'active')
            ->first();

        if (! $seller) {
            abort(404, 'Store not found.');
        }

        // Set Spatie tenant context — required by QuickCheckout Livewire (uses Tenant::current()).
        // EnsureSellerTenantForgotten::terminate() cleans this up after response is sent.
        $seller->makeCurrent();

        // Serve from cache (key: seller_id — stable across shop_slug renames).
        $page = Cache::remember(
            SellerPage::cacheKeyFor($seller->id),
            self::CACHE_TTL,
            fn () => SellerPage::where('seller_id', $seller->id)
                ->where('is_published', true)
                ->first(),
        );

        if (! $page) {
            abort(404, 'Store page is not published yet.');
        }

        return view('storefront.seller.index', [
            'page' => $page,
            'tenant' => $seller,
            'isPreview' => false,
        ]);
    }

    /**
     * Redirect subdomain traffic to the canonical /shop/{shop_slug} path (301).
     *
     * ADR-SC1: subdomain route is secondary. Tenant is already resolved by
     * NeedsTenant middleware via SubdomainTenantFinder. This action is purely
     * a redirect — stateless, no render, no cache lookup.
     *
     * 301 (Permanent) is intentional — instructs search engines to transfer
     * PageRank to the /shop/ canonical URL.
     */
    public function subdomainRedirect(Request $request, string $seller_subdomain): RedirectResponse
    {
        $tenant = Tenant::current();

        if (! $tenant || ! $tenant->shop_slug) {
            abort(404, 'Store not found.');
        }

        return redirect()->route('seller.storefront.shop', [
            'shop_slug' => $tenant->shop_slug,
        ], 301);
    }

    /**
     * Display the preview of the Seller Storefront for the Filament panel iframe.
     *
     * Preview intentionally bypasses cache — it must always show the latest
     * unsaved/unpublished draft to the authenticated seller.
     *
     * Note: preview() uses auth()->user()->sellerProfile directly (not Filament::getTenant())
     * because it runs outside the Filament panel context (plain auth middleware).
     */
    public function preview(Request $request): mixed
    {
        $user = auth()->user();

        if (! $user || ! $user->sellerProfile) {
            abort(403, 'Unauthorized.');
        }

        $tenant = $user->sellerProfile;

        // Fetch latest data directly — no cache for preview.
        $page = SellerPage::where('seller_id', $tenant->id)->first();

        if (! $page) {
            return response('No page configured yet. Please configure and save.', 200);
        }

        return view('storefront.seller.index', [
            'page' => $page,
            'tenant' => $tenant,
            'isPreview' => true,
        ]);
    }
}
