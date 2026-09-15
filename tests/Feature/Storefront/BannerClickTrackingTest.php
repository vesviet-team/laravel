<?php

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('tracking route increments banner clicks_count by 1', function () {
    $banner = Banner::factory()->hero()->active()->create([
        'clicks_count' => 0,
        'link' => '/products',
    ]);

    expect($banner->clicks_count)->toBe(0);

    $response = $this->get(route('banner.click', $banner->id));

    $response->assertRedirect('/products');

    $banner->refresh();
    expect($banner->clicks_count)->toBe(1);

    // Second click
    $this->get(route('banner.click', $banner->id));
    $banner->refresh();
    expect($banner->clicks_count)->toBe(2);
});

test('tracking route redirects to relative internal url', function () {
    $banner = Banner::factory()->promo2Col()->active()->create([
        'link' => '/catalog?category=living-room',
    ]);

    $response = $this->get(route('banner.click', $banner->id));

    $response->assertRedirect('/catalog?category=living-room');
});

test('tracking route redirects away to external https url', function () {
    $banner = Banner::factory()->collection3Col()->active()->create([
        'link' => 'https://partner-brand.example.com/nordic-exclusive',
    ]);

    $response = $this->get(route('banner.click', $banner->id));

    $response->assertRedirect('https://partner-brand.example.com/nordic-exclusive');
});

test('tracking route redirects to home when banner link is null or empty', function () {
    $nullLinkBanner = Banner::factory()->hero()->active()->create([
        'link' => null,
    ]);

    $responseNull = $this->get(route('banner.click', $nullLinkBanner->id));
    $responseNull->assertRedirect(route('home'));

    $emptyLinkBanner = Banner::factory()->hero()->active()->create([
        'link' => '',
    ]);

    $responseEmpty = $this->get(route('banner.click', $emptyLinkBanner->id));
    $responseEmpty->assertRedirect(route('home'));

    $whitespaceLinkBanner = Banner::factory()->hero()->active()->create([
        'link' => '   ',
    ]);

    $responseWhitespace = $this->get(route('banner.click', $whitespaceLinkBanner->id));
    $responseWhitespace->assertRedirect(route('home'));
});

test('tracking route sanitizes and blocks dangerous pseudo-protocol uri schemes', function () {
    // 1. standard javascript: scheme
    $jsBanner = Banner::factory()->hero()->active()->create([
        'link' => 'javascript:alert(1)',
    ]);
    $responseJs = $this->get(route('banner.click', $jsBanner->id));
    $responseJs->assertRedirect(route('home'));

    // 2. uppercase and whitespace padded JAVASCRIPT: scheme
    $jsBanner2 = Banner::factory()->hero()->active()->create([
        'link' => '   JAVASCRIPT:alert(document.cookie)',
    ]);
    $responseJs2 = $this->get(route('banner.click', $jsBanner2->id));
    $responseJs2->assertRedirect(route('home'));

    // 3. data: URI scheme
    $dataBanner = Banner::factory()->hero()->active()->create([
        'link' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    ]);
    $responseDate = $this->get(route('banner.click', $dataBanner->id));
    $responseDate->assertRedirect(route('home'));

    // 4. vbscript: URI scheme
    $vbsBanner = Banner::factory()->hero()->active()->create([
        'link' => 'vbscript:msgbox(1)',
    ]);
    $responseVbs = $this->get(route('banner.click', $vbsBanner->id));
    $responseVbs->assertRedirect(route('home'));
});

test('tracking route returns 404 for non-existent banner', function () {
    $response = $this->get('/banner/click/999999');

    $response->assertNotFound();
});

test('tracking route does not invalidate home_banners cache', function () {
    $cachedPayload = [
        'heroSlides' => collect(['cached_hero']),
        'promoBanners' => collect(['cached_promo']),
        'collectionBanners' => collect(['cached_collection']),
    ];

    $banner = Banner::factory()->hero()->active()->create([
        'link' => '/products',
    ]);

    // Populate cache
    Cache::put('home_banners', $cachedPayload, 3600);
    expect(Cache::has('home_banners'))->toBeTrue();

    // Hit the click tracking route
    $response = $this->get(route('banner.click', $banner->id));
    $response->assertRedirect('/products');

    // Confirm cache is untouched
    expect(Cache::has('home_banners'))->toBeTrue();
    expect(Cache::get('home_banners'))->toBe($cachedPayload);
});
