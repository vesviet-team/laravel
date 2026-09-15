<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;

class SyncSpatieTenantWithFilament
{
    /**
     * Handle an incoming request.
     *
     * Ensures that the Filament active tenant is also set as the current
     * Spatie Multitenancy tenant. This allows shared scopes (like TenantSellerScope)
     * and actions to work transparently within the Filament panel.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $tenant = Filament::getTenant();

        if ($tenant && $tenant instanceof Tenant) {
            $tenant->makeCurrent();
        }

        return $next($request);
    }
}
