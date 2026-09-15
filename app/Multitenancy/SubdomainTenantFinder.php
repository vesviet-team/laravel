<?php

namespace App\Multitenancy;

use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

/**
 * SubdomainTenantFinder resolves Spatie tenant from request subdomain.
 *
 * Example: host=anh.demo.tanhdev.com, APP_URL=https://demo.tanhdev.com
 *   -> subdomain="anh" -> SellerProfile::where('subdomain','anh')->first()
 */
class SubdomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? '';

        if (empty($appHost) || ! str_ends_with($host, '.'.$appHost)) {
            return null;
        }

        $subdomain = substr($host, 0, strlen($host) - strlen('.'.$appHost));

        if (empty($subdomain)) {
            return null;
        }

        return SellerProfile::where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();
    }
}
