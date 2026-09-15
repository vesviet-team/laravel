<?php

namespace App\Providers\Filament;

use App\Filament\Seller\Pages\Auth\Register;
use App\Filament\Seller\Pages\Tenancy\EditSellerProfile;
use App\Http\Middleware\SyncSpatieTenantWithFilament;
use App\Models\SellerProfile;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('seller')
            ->path('seller')
            ->authGuard('web')
            ->login()
            ->registration(Register::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Seller/Resources'), for: 'App\\Filament\\Seller\\Resources')
            ->discoverPages(in: app_path('Filament/Seller/Pages'), for: 'App\\Filament\\Seller\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Seller/Widgets'), for: 'App\\Filament\\Seller\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->navigationGroups([
                'Bán hàng',
                'Sản phẩm',
                'Cửa hàng',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->tenant(SellerProfile::class, ownershipRelationship: 'seller', slugAttribute: 'subdomain')
            ->tenantProfile(EditSellerProfile::class)
            ->profile()
            ->tenantMiddleware([
                SyncSpatieTenantWithFilament::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => Blade::render('
                    @if(auth()->check() && request()->is("seller*"))
                        <div class="seller-mobile-bottom-bar">
                            <a href="/seller" class="{{ request()->is("seller") ? "active" : "" }}">
                                <x-heroicon-o-home />
                                <span>Tổng quan</span>
                            </a>
                            <a href="/seller/simple-products" class="{{ request()->is("seller/simple-products*") ? "active" : "" }}">
                                <x-heroicon-o-cube />
                                <span>Sản phẩm</span>
                            </a>
                        </div>
                    @endif
                ')
            );
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<link rel="stylesheet" href="/css/seller-panel.css" />',
        );

        // Seller theme CSS is injected via direct link tag instead of
        // viteTheme() to avoid Vite manifest coupling — the theme is
        // a small Tailwind layer and shipping it as a plain CSS file
        // (pre-built and committed to public/css/) removes the build
        // dependency for Filament rendering.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<link rel="stylesheet" href="/css/seller-theme.css" />',
        );
    }
}
