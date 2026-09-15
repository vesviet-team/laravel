<?php

use App\Rules\ReservedRouteRule;

test('valid custom slugs pass validation', function (string $slug) {
    $rule = new ReservedRouteRule;
    $failed = false;
    $failureMessage = null;

    $rule->validate('slug', $slug, function ($message) use (&$failed, &$failureMessage) {
        $failed = true;
        $failureMessage = $message;
    });

    expect($failed)->toBeFalse();
    expect($failureMessage)->toBeNull();
})->with([
    'xu-huong-noi-that-2026',
    'ban-an-go-soi-nhap-khau',
    'nghe-thuat-bai-tri-anh-sang',
    'chinh-sach-bao-hanh-10-nam',
    'cam-nang-chon-sofa-da',
    'meo-ve-sinh-ban-tra-mat-da',
    'review-ghe-an-scandinavian',
]);

test('reserved system slugs fail validation', function (string $slug) {
    $rule = new ReservedRouteRule;
    $failed = false;
    $failureMessage = null;

    $rule->validate('slug', $slug, function ($message) use (&$failed, &$failureMessage) {
        $failed = true;
        $failureMessage = $message;
    });

    expect($failed)->toBeTrue();
    expect($failureMessage)->toContain('conflicts with a reserved system route');
})->with([
    'admin',
    'blog',
    'products',
    'categories',
    'cart',
    'checkout',
    'login',
    'register',
    'logout',
    'api',
    'about',
    'contact',
    'track-order',
    'account',
    'newsletter',
    'wishlist',
    'order-tracking',
    'password',
    'forgot-password',
    'reset-password',
    'storage',
    'livewire',
    'up',
    'horizon',
    'telescope',
    'shield',
    'settings',
    'sanctum',
    'oauth',
    'health',
]);

test('reserved slugs fail case-insensitively', function (string $slug) {
    $rule = new ReservedRouteRule;
    $failed = false;

    $rule->validate('slug', $slug, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
})->with([
    'ADMIN',
    'Blog',
    'PRODUCTS',
    'Cart',
    'CheckOut',
    'LOGIN',
    'Register',
    'API',
    'SHIELD',
]);

test('reserved slugs with leading or trailing slashes and whitespace fail validation', function (string $slug) {
    $rule = new ReservedRouteRule;
    $failed = false;

    $rule->validate('slug', $slug, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
})->with([
    '/admin',
    'admin/',
    '/blog/',
    '//cart//',
    ' checkout ',
    ' /products/ ',
    '   api   ',
]);

test('getReservedSlugs returns complete list of protected system routes', function () {
    $rule = new ReservedRouteRule;
    $reserved = $rule->getReservedSlugs();

    expect($reserved)->toBeArray();
    expect($reserved)->toContain('admin', 'blog', 'products', 'cart', 'checkout', 'shield', 'livewire');
});
