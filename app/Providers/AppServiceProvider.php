<?php

namespace App\Providers;

use App\Events\OrderPlaced;
use App\Listeners\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Models\SellerPage;
use App\Models\User;
use App\Observers\ProductObserver;
use App\Observers\PromotionRuleObserver;
use App\Policies\SellerOrderPolicy;
use App\Policies\SellerPagePolicy;
use App\Policies\SellerProductPolicy;
use App\Settings\GeneralSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Override Number::format to use a plain PHP fallback when the
     * PHP "intl" extension is not installed (common on shared cPanel hosting).
     */
    public function boot(): void
    {
        // Super Admin bypass for all Shield and policy permission gates.
        // Instance guard: gates may also be evaluated for non-User
        // authenticatables (e.g. Customer on the customer guard) — they must
        // fall through to normal policy evaluation instead of fataling.
        Gate::before(function ($user, $ability): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            return $user->hasRole(config('filament-shield.super_admin.name')) ? true : null;
        });

        if (! extension_loaded('intl')) {
            // Macro overrides Number::format() with a plain number_format fallback.
            // This prevents RuntimeException on hosts without the intl extension.
            Number::macro('format', function (int|float $number, ?int $precision = null, ?int $maxPrecision = null, ?string $locale = null): string|false {
                $decimals = $precision ?? ($maxPrecision ?? 0);

                return number_format((float) $number, $decimals, '.', ',');
            });
        }

        // Seller Panel authorization policies (SF-01, SF-02, P1-03)
        // SellerProductPolicy — scopes products to active seller tenant
        // SellerOrderPolicy   — prevents order deletion, scopes to seller tenant
        // SellerPagePolicy    — prevents cross-seller page access (P1-03)
        Gate::policy(Product::class, SellerProductPolicy::class);
        Gate::policy(Order::class, SellerOrderPolicy::class);
        Gate::policy(SellerPage::class, SellerPagePolicy::class);

        // A1: Domain Event bindings
        // OrderPlaced fires after ProcessCheckoutAction or ProcessLandingOrderAction commits.
        // The listener is queued (ShouldQueue) — runs via 'database' queue worker (ADR-03).
        Event::listen(OrderPlaced::class, SendOrderConfirmationEmail::class);

        // Observers: invalidate cached promotional prices and homepage data
        Product::observe(ProductObserver::class);
        PromotionRule::observe(PromotionRuleObserver::class);

        // Rate Limiters (S4)
        // Checkout form: max 10 submissions per minute per IP
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())
                ->response(fn () => back()
                    ->with('error', 'Quá nhiều yêu cầu. Vui lòng thử lại sau 1 phút.')
                    ->withInput());
        });

        // Landing order form: max 5 submissions per minute per IP (tighter — no auth)
        RateLimiter::for('landing-order', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())
                ->response(fn () => back()
                    ->with('error', 'Quá nhiều yêu cầu. Vui lòng thử lại sau 1 phút.')
                    ->withInput());
        });

        // Newsletter: max 10 submissions per minute per IP
        RateLimiter::for('newsletter', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())
                ->response(fn () => back()->withErrors([
                    'email' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
                ]));
        });

        // Expose settings to all views
        try {
            View::share('globalSettings', app(GeneralSettings::class));
        } catch (\Exception $e) {
            // Settings might not be migrated yet
        }
    }
}
