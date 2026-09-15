<?php

use App\Http\Controllers\Admin\OrderPdfController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\AuthController;
use App\Http\Controllers\Storefront\BannerClickController;
use App\Http\Controllers\Storefront\BlogController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\FeedController;
use App\Http\Controllers\Storefront\HomepageController;
use App\Http\Controllers\Storefront\NewsletterController;
use App\Http\Controllers\Storefront\NotificationPreferenceController;
use App\Http\Controllers\Storefront\OrderTrackingController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\PrivacyController;
use App\Http\Controllers\Storefront\ProfileController;
use App\Http\Controllers\Storefront\ReferralController;
use App\Http\Controllers\Storefront\SellerStorefrontController;
use App\Http\Controllers\Storefront\SessionController;
use App\Http\Middleware\EnsureSellerTenantForgotten;
use App\Livewire\CheckoutFlow;
use App\Livewire\WishlistPage;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

$appDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

// ─────────────────────────────────────────────────────────────────────────────
// Seller Storefront Routing (ADR-SC1)
// ─────────────────────────────────────────────────────────────────────────────

// Path-based (canonical / primary) — works in ALL environments (local, staging, production)
// ADR-SC1: ->where constraint is MANDATORY — prevents path traversal and XSS.
// EnsureSellerTenantForgotten::terminate() cleans up Spatie tenant context after response.
Route::get('/shop/{shop_slug}', [SellerStorefrontController::class, 'shopPath'])
    ->name('seller.storefront.shop')
    ->where('shop_slug', '[a-z0-9\-]+')
    ->middleware([EnsureSellerTenantForgotten::class]);

// Subdomain-based (secondary) — only works on production with wildcard DNS.
// 301 redirect to /shop canonical. Stateless — no render, no cache.
Route::domain('{seller_subdomain}.'.$appDomain)
    ->middleware([NeedsTenant::class])
    ->group(function () {
        Route::get('/', [SellerStorefrontController::class, 'subdomainRedirect'])->name('seller.storefront.index');
    });

// Seller Storefront Preview Route (For Filament Iframe)
Route::get('/seller/preview/page', [SellerStorefrontController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('seller.storefront.preview');

Route::get('/', [HomepageController::class, 'index'])->name('home');
Route::view('/about', 'storefront.pages.about')->name('about');
Route::view('/contact', 'storefront.pages.contact')->name('contact');

// Fallback login route for auth middleware
Route::get('/login', fn () => redirect()->route('account.login'))->name('login');

// Blog & Knowledge Hub
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Catalog
Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [CatalogController::class, 'show'])->name('products.show');

// Checkout
Route::get('/checkout', CheckoutFlow::class)->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:checkout');
Route::get('/checkout/success/{order_number}', [CheckoutController::class, 'success'])->name('checkout.success');

// Order Tracking
Route::get('/track-order', [OrderTrackingController::class, 'index'])->name('track-order.index');
Route::post('/track-order', [OrderTrackingController::class, 'track'])->name('track-order.track')->middleware('throttle:20,1');

// Newsletter
Route::post('/newsletter/subscribe', [NewsletterController::class, 'store'])->name('newsletter.subscribe')->middleware('throttle:newsletter');

// Auth (Customer)
Route::prefix('account')->name('account.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        // B1: Forgot password flow
        Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
        Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
        Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

        // Two-Factor Authentication Challenge (after successful password auth)
        Route::get('/2fa/challenge', [AuthController::class, 'showTwoFactorChallenge'])->name('2fa.challenge');
        Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor'])->name('2fa.verify')->middleware('throttle:10,1');
    });

    Route::middleware(['auth:customer', 'concurrent.sessions'])->group(function () {
        Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
        Route::get('/orders/{order_number}', [AccountController::class, 'orderDetail'])->name('orders.show');
        Route::post('/orders/{order_number}/cancel', [AccountController::class, 'cancelOrder'])->name('orders.cancel');
        Route::post('/orders/{order_number}/reorder', [AccountController::class, 'reorder'])->name('orders.reorder');
        Route::get('/wishlist', WishlistPage::class)->name('wishlist');
        Route::post('/products/{product}/reviews', [ProductReviewController::class, 'store'])->name('products.reviews.store');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Profile Management
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');
        Route::delete('/profile', [ProfileController::class, 'deleteAccount'])->name('profile.delete');

        // Address Management
        Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
        Route::get('/addresses/create', [AddressController::class, 'create'])->name('addresses.create');
        Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::get('/addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
        Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::put('/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('addresses.default');

        // Two-Factor Authentication Management
        Route::get('/two-factor', [ProfileController::class, 'showTwoFactor'])->name('two-factor');
        Route::post('/two-factor/enable', [ProfileController::class, 'enableTwoFactor'])->name('two-factor.enable')->middleware('throttle:5,1');
        Route::post('/two-factor/disable', [ProfileController::class, 'disableTwoFactor'])->name('two-factor.disable')->middleware('throttle:5,1');
        Route::post('/two-factor/regenerate-recovery-codes', [ProfileController::class, 'regenerateRecoveryCodes'])->name('two-factor.regenerate-recovery-codes')->middleware('throttle:5,1');

        // Session Management
        Route::get('/sessions', [SessionController::class, 'index'])->name('sessions');
        Route::delete('/sessions/{sessionId}', [SessionController::class, 'destroy'])->name('sessions.destroy');
        Route::delete('/sessions', [SessionController::class, 'destroyAll'])->name('sessions.destroy-all');

        // Notification Preferences
        Route::get('/notifications', [NotificationPreferenceController::class, 'index'])->name('notifications');
        Route::put('/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.update');

        // Referral & Loyalty
        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals');
        Route::get('/referrals/share', [ReferralController::class, 'share'])->name('referrals.share');

        // Privacy & GDPR
        Route::get('/privacy', [PrivacyController::class, 'index'])->name('privacy');
        Route::get('/privacy/export', [PrivacyController::class, 'exportData'])->name('privacy.export');
        Route::delete('/privacy', [PrivacyController::class, 'deleteAccount'])->name('privacy.delete');
    });
});

// Admin Route for PDF — protected by web (Filament) auth guard.
// Authorization: OrderPolicy::view → 'view_order' permission (super_admin bypasses).
Route::middleware('auth')->group(function () {
    Route::get('/admin/orders/{order}/pdf', [OrderPdfController::class, 'download'])->name('admin.orders.pdf');
});

// SEO Feeds & XML Sitemaps
Route::get('/sitemap.xml', [FeedController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemap-products.xml', [FeedController::class, 'productsSitemap'])->name('sitemap.products');
Route::get('/sitemap-categories.xml', [FeedController::class, 'categoriesSitemap'])->name('sitemap.categories');
Route::get('/sitemap-posts.xml', [FeedController::class, 'postsSitemap'])->name('sitemap.posts');
Route::get('/sitemap-pages.xml', [FeedController::class, 'pagesSitemap'])->name('sitemap.pages');
Route::get('/feeds/google-merchant.xml', [FeedController::class, 'googleMerchantFeed'])->name('feed.google-merchant');
Route::get('/feed', [FeedController::class, 'blogRssFeed'])->name('feed.rss');
Route::get('/rss.xml', [FeedController::class, 'blogRssFeed'])->name('feed.rss.xml');

// Banner Click Tracking
Route::get('/banner/click/{banner}', [BannerClickController::class, 'track'])->name('banner.click');

// Dynamic CMS & Landing Page Route (Must be at the very end to avoid conflicts)
Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
Route::get('/{slug}', [PageController::class, 'show'])->name('page.show');
