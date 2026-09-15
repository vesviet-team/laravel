<?php

use App\Actions\ProcessSellerQuickOrderAction;
use App\Events\SellerOrderPlaced;
use App\Exceptions\SellerActionException;
use App\Models\Product;
use App\Models\SellerPage;
use App\Models\SellerProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('tenant isolation correctly scopes products', function () {
    $sellerA = SellerProfile::factory()->create(['subdomain' => 'shop-a']);
    $sellerB = SellerProfile::factory()->create(['subdomain' => 'shop-b']);

    $productA = Product::factory()->create(['seller_id' => $sellerA->id]);
    $productB = Product::factory()->create(['seller_id' => $sellerB->id]);

    $sellerA->makeCurrent();

    $products = Product::all();

    expect($products)->toHaveCount(1)
        ->and($products->first()->id)->toBe($productA->id)
        ->and($products->first()->seller_id)->toBe($sellerA->id);
});

test('concurrent stock lock prevents overselling', function () {
    Event::fake([SellerOrderPlaced::class]);

    $seller = SellerProfile::factory()->create();
    $seller->makeCurrent();

    $product = Product::factory()->create([
        'seller_id' => $seller->id,
        'stock' => 1,
    ]);

    $action = app(ProcessSellerQuickOrderAction::class);

    // First purchase succeeds
    $order = $action->execute($seller, [
        'product_id' => $product->id,
        'quantity' => 1,
        'customer_name' => 'John Doe',
        'phone' => '0900000000',
        'address' => '123 Test St',
    ]);

    expect($order->order_number)->toStartWith('ORD-');

    // Second purchase must throw SellerActionException
    expect(fn () => $action->execute($seller, [
        'product_id' => $product->id,
        'quantity' => 1,
        'customer_name' => 'Jane Doe',
        'phone' => '0911111111',
        'address' => '456 Test St',
    ]))->toThrow(SellerActionException::class, 'Sản phẩm đã hết hàng hoặc không đủ số lượng.');
});

test('carrd subdomain routing falls back correctly', function () {
    $seller = SellerProfile::factory()->create(['subdomain' => 'my-store']);

    $response = $this->get('http://my-store.localhost/seller');
    $response->assertStatus(302)->assertRedirectContains('/seller/login');
});

test('subdomain switch sets current tenant', function () {
    $seller = SellerProfile::factory()->create(['subdomain' => 'my-store']);

    expect(Tenant::current())->toBeNull();

    $seller->makeCurrent();

    expect(Tenant::current())->not->toBeNull()
        ->and(Tenant::current()->id)->toBe($seller->id);
});

test('SellerProfile generates unique non-reserved subdomains', function () {
    $shopName = 'Acme Shop';

    $seller = SellerProfile::factory()->create([
        'subdomain' => (new SellerProfile)->generateUniqueSubdomain($shopName),
    ]);

    // Reserved subdomains must never be returned.
    expect(SellerProfile::RESERVED_SUBDOMAINS)->not->toContain($seller->subdomain);
    expect($seller->subdomain)->toMatch('/^[a-z0-9-]+$/');
});

test('SellerPage cache key uses CACHE_KEY_PREFIX constant', function () {
    // ADR-SC1: cache key uses seller_id (stable int), not subdomain (mutable string).
    // Format: 'storefront:page:{seller_id}'
    expect(SellerPage::cacheKeyFor(42))->toBe('storefront:page:42');
});

test('SellerProfile hasCompleteBankInfo requires all three fields', function () {
    $seller = SellerProfile::factory()->create([
        'bank_code' => 'VCB',
        'bank_account_no' => '1234',
        'bank_account_name' => 'NGUYEN VAN A',
    ]);

    expect($seller->hasCompleteBankInfo())->toBeTrue();

    $seller->bank_account_name = null;
    expect($seller->hasCompleteBankInfo())->toBeFalse();
});

test('User canAccessPanel differentiates seller vs admin panels', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create();

    $sellerPanel = Filament::getPanel('seller');
    $adminPanel = Filament::getPanel('admin');

    expect($user->canAccessPanel($sellerPanel))->toBeFalse();

    $user->assignRole('super_admin');
    expect($user->canAccessPanel($adminPanel))->toBeTrue();

    $seller = SellerProfile::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    // Refresh the user model so the new relation is loaded.
    $user->refresh();
    expect($user->sellerProfile)->not->toBeNull();
    expect($user->canAccessPanel($sellerPanel))->toBeTrue();
});
