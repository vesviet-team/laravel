<?php

namespace App\Tenancy\TenantFinders;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class SubdomainOrSlugTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();

        $mainHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        if ($host !== $mainHost && str_ends_with($host, '.'.$mainHost)) {
            $subdomain = str_replace('.'.$mainHost, '', $host);
            $tenant = Tenant::where('subdomain', $subdomain)->first();

            if ($tenant) {
                return $tenant;
            }
        }

        // Fallback: check path prefix for /@shop_name
        $path = $request->path();
        if (preg_match('/^@([a-zA-Z0-9\-]+)/', $path, $matches)) {
            $slug = $matches[1];

            return Tenant::where('subdomain', $slug)->first();
        }

        return null;
    }
}
