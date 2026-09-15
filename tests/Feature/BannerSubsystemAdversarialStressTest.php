<?php

use App\Models\Banner;
use Database\Seeders\BannerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/*
|--------------------------------------------------------------------------
| VECTOR 1: EMPTY DB FALLBACK RENDERING ON STOREFRONT HOMEPAGE
|--------------------------------------------------------------------------
*/

test('vector 1: empty DB renders all three banner fallback sections without breaking layout or 500 error', function () {
    expect(Banner::count())->toBe(0);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewIs('storefront.home.index');
    $response->assertViewHas('heroSlides', fn ($v) => $v->isEmpty());
    $response->assertViewHas('promoBanners', fn ($v) => $v->isEmpty());
    $response->assertViewHas('collectionBanners', fn ($v) => $v->isEmpty());

    $content = $response->getContent();

    // 1. Hero fallback content
    expect($content)->toContain('Hero banner')
        ->toContain('role="region"')
        ->toContain('aria-roledescription="carousel"')
        ->toContain('Bộ Sưu Tập Mới')
        ->toContain('Thiết Kế Hiện Đại');

    // 2. Promo 2-col fallback content
    expect($content)->toContain('Lighting on Express · Bộ Sưu Tập')
        ->toContain('Phong Cách Mùa Này')
        ->toContain('Dining Chairs · Chất Liệu Cao Cấp')
        ->toContain('Thiết Kế Tối Giản');

    // 3. Collection 3-col fallback content
    expect($content)->toContain('Curated Spaces')
        ->toContain('Copenhague Desk · Danh Mục 01')
        ->toContain('Đồ Nội Thất')
        ->toContain('Cement Wood Lamp · Danh Mục 02')
        ->toContain('Trang Trí Nhà')
        ->toContain('Arte 60 Stool · Danh Mục 03')
        ->toContain('Phụ Kiện');
});

/*
|--------------------------------------------------------------------------
| VECTOR 2: PARTIAL POSITION POPULATION FALLBACK RENDERING
|--------------------------------------------------------------------------
*/

test('vector 2a: partial population - only hero in DB; promo and collection fall back gracefully', function () {
    $hero = Banner::factory()->hero()->active()->create([
        'title' => 'Custom Hero Only',
    ]);

    expect(Banner::where('position', Banner::POSITION_HOME_PROMO_2COL)->count())->toBe(0);
    expect(Banner::where('position', Banner::POSITION_HOME_COLLECTION_3COL)->count())->toBe(0);

    $response = $this->get(route('home'));
    $response->assertOk();

    $content = $response->getContent();

    // Dynamic Hero
    expect($content)->toContain('Custom Hero Only');
    // Fallback Promo
    expect($content)->toContain('Lighting on Express · Bộ Sưu Tập');
    // Fallback Collection
    expect($content)->toContain('Copenhague Desk · Danh Mục 01');
});

test('vector 2b: partial population - only promo in DB; hero and collection fall back gracefully', function () {
    $promo = Banner::factory()->promo2Col()->active()->create([
        'title' => 'Custom Promo Only',
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $content = $response->getContent();

    // Fallback Hero
    expect($content)->toContain('Bộ Sưu Tập Mới');
    // Dynamic Promo
    expect($content)->toContain('Custom Promo Only');
    // Fallback Collection
    expect($content)->toContain('Copenhague Desk · Danh Mục 01');
});

test('vector 2c: partial population - only collection in DB; hero and promo fall back gracefully', function () {
    $col = Banner::factory()->collection3Col()->active()->create([
        'title' => 'Custom Collection Only',
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $content = $response->getContent();

    // Fallback Hero
    expect($content)->toContain('Bộ Sưu Tập Mới');
    // Fallback Promo
    expect($content)->toContain('Lighting on Express · Bộ Sưu Tập');
    // Dynamic Collection
    expect($content)->toContain('Custom Collection Only');
});

test('vector 2d: underpopulated positions - single promo card (1 instead of 2) renders without error', function () {
    $singlePromo = Banner::factory()->promo2Col()->active()->create([
        'title' => 'Solo Promo Card',
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('Solo Promo Card');
});

test('vector 2e: underpopulated positions - single collection card (1 instead of 3) renders without error', function () {
    $singleCol = Banner::factory()->collection3Col()->active()->create([
        'title' => 'Solo Collection Card',
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('Solo Collection Card');
});

/*
|--------------------------------------------------------------------------
| VECTOR 3: CONCURRENCY ON CLICK TRACKING AND CACHE PRESERVATION
|--------------------------------------------------------------------------
*/

test('vector 3a: click tracking atomic increment updates clicks_count accurately across multiple rapid clicks', function () {
    $banner = Banner::factory()->hero()->active()->create([
        'clicks_count' => 0,
        'link' => '/products',
    ]);

    // Simulate 25 sequential rapid clicks
    for ($i = 0; $i < 25; $i++) {
        $response = $this->get(route('banner.click', $banner->id));
        $response->assertRedirect('/products');
    }

    $banner->refresh();
    expect($banner->clicks_count)->toBe(25);
});

test('vector 3b: click tracking does NOT trigger BannerObserver and preserves home_banners cache', function () {
    $banner = Banner::factory()->hero()->active()->create([
        'title' => 'Cached Hero Slide',
        'link' => '/catalog',
    ]);

    // Seed cache
    $this->get(route('home'))->assertOk();
    expect(Cache::has('home_banners'))->toBeTrue();

    $cachedPayload = Cache::get('home_banners');
    expect($cachedPayload['heroSlides']->first()->title)->toBe('Cached Hero Slide');

    // Click tracking on banner
    $this->get(route('banner.click', $banner->id))->assertRedirect('/catalog');

    // Cache MUST STILL EXIST and not be invalidated by click tracking
    expect(Cache::has('home_banners'))->toBeTrue();

    // Normal model update MUST invalidate cache
    $banner->title = 'Updated Title';
    $banner->save();

    expect(Cache::has('home_banners'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| VECTOR 4: SECURITY - MALICIOUS SCHEMES, OPEN REDIRECTS, AND XSS
|--------------------------------------------------------------------------
*/

test('vector 4a: security - blocks javascript: URI schemes and redirects safely to home', function ($maliciousUri) {
    $banner = Banner::factory()->hero()->active()->create([
        'link' => $maliciousUri,
    ]);

    $response = $this->get(route('banner.click', $banner->id));
    $response->assertRedirect(route('home'));
})->with([
    'javascript:alert("XSS")',
    'JAVASCRIPT:alert(1)',
    'JavaScript:document.location="https://attacker.com"',
    'javascript:/*--></title></style></textarea>*/<script>alert(1)</script>',
]);

test('vector 4b: security - blocks data: and vbscript: URI schemes and redirects to home', function ($maliciousUri) {
    $banner = Banner::factory()->hero()->active()->create([
        'link' => $maliciousUri,
    ]);

    $response = $this->get(route('banner.click', $banner->id));
    $response->assertRedirect(route('home'));
})->with([
    'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'DATA:text/html,<script>alert(1)</script>',
    'vbscript:msgbox("hello")',
    'VBSCRIPT:MsgBox(1)',
]);

test('vector 4c: security - handles null, empty, and whitespace-only links gracefully by redirecting to home', function ($emptyLink) {
    $banner = Banner::factory()->hero()->active()->create([
        'link' => $emptyLink,
    ]);

    $response = $this->get(route('banner.click', $banner->id));
    $response->assertRedirect(route('home'));
})->with([
    null,
    '',
    '   ',
    "\t\n",
]);

test('vector 4d: security - redirects away for external http/https URLs and internal redirect for relative URLs', function () {
    $externalBanner = Banner::factory()->hero()->active()->create([
        'link' => 'https://partner-brand.com/campaign-2026',
    ]);

    $internalBanner = Banner::factory()->hero()->active()->create([
        'link' => '/catalog?category=living-room',
    ]);

    $responseExt = $this->get(route('banner.click', $externalBanner->id));
    $responseExt->assertRedirect('https://partner-brand.com/campaign-2026');

    $responseInt = $this->get(route('banner.click', $internalBanner->id));
    $responseInt->assertRedirect('/catalog?category=living-room');
});

test('vector 4e: security - XSS injection in banner text attributes is safely escaped in HTML output', function () {
    $xssTitle = '<script>alert("XSS-TITLE")</script>';
    $xssEyebrow = '<img src=x onerror=alert("XSS-EYEBROW")>';
    $xssSubtitle = '"><script>alert("XSS-SUB")</script>';
    $xssCta = '<b>Click</b><script>alert(1)</script>';

    Banner::factory()->promo2Col()->active()->create([
        'title' => $xssTitle,
        'eyebrow' => $xssEyebrow,
        'subtitle' => $xssSubtitle,
        'cta_text' => $xssCta,
    ]);

    Banner::factory()->collection3Col()->active()->create([
        'title' => $xssTitle,
        'eyebrow' => $xssEyebrow,
        'subtitle' => $xssSubtitle,
        'cta_text' => $xssCta,
    ]);

    $response = $this->get(route('home'));
    $response->assertOk();
    $content = $response->getContent();

    // Raw script tags MUST NOT be present in executable form in the HTML body
    expect($content)->not->toContain('<script>alert("XSS-TITLE")</script>')
        ->not->toContain('<img src=x onerror=alert("XSS-EYEBROW")>')
        ->not->toContain('"><script>alert("XSS-SUB")</script>');

    // Must be escaped as HTML entities
    expect($content)->toContain('&lt;script&gt;alert(&quot;XSS-TITLE&quot;)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(&quot;XSS-EYEBROW&quot;)&gt;');
});

/*
|--------------------------------------------------------------------------
| VECTOR 5: SEEDER IDEMPOTENCY OVER MULTIPLE RUNS
|--------------------------------------------------------------------------
*/

test('vector 5: BannerSeeder is 100% idempotent across 5 consecutive runs with exact count and position distribution', function () {
    $seeder = new BannerSeeder;

    for ($run = 1; $run <= 5; $run++) {
        $seeder->run();

        expect(Banner::count())->toBe(7);
        expect(Banner::where('position', Banner::POSITION_HERO_SLIDER)->count())->toBe(2);
        expect(Banner::where('position', Banner::POSITION_HOME_PROMO_2COL)->count())->toBe(2);
        expect(Banner::where('position', Banner::POSITION_HOME_COLLECTION_3COL)->count())->toBe(3);
    }
});

/*
|--------------------------------------------------------------------------
| VECTOR 6: MODEL SCOPES AND SCHEDULING ADVERSARIAL MATRIX
|--------------------------------------------------------------------------
*/

test('vector 6: Model scopes handle boundary datetime conditions and composite indexing filters', function () {
    $baseTime = Carbon::parse('2026-08-20 12:00:00');
    Carbon::setTestNow($baseTime);

    // Exact now starts_at -> Active
    $bExactStart = Banner::factory()->hero()->active()->create([
        'title' => 'Starts Exact Now',
        'starts_at' => $baseTime,
        'ends_at' => null,
    ]);

    // Exact now ends_at -> Active
    $bExactEnd = Banner::factory()->hero()->active()->create([
        'title' => 'Ends Exact Now',
        'starts_at' => null,
        'ends_at' => $baseTime,
    ]);

    // 1 second in future starts_at -> Inactive
    $bFutureStart = Banner::factory()->hero()->active()->create([
        'title' => 'Starts Future',
        'starts_at' => $baseTime->copy()->addSecond(),
        'ends_at' => null,
    ]);

    // 1 second in past ends_at -> Inactive
    $bPastEnd = Banner::factory()->hero()->active()->create([
        'title' => 'Ends Past',
        'starts_at' => null,
        'ends_at' => $baseTime->copy()->subSecond(),
    ]);

    $activeBanners = Banner::active()->pluck('title')->all();

    expect($activeBanners)->toContain('Starts Exact Now')
        ->toContain('Ends Exact Now')
        ->not->toContain('Starts Future')
        ->not->toContain('Ends Past');

    Carbon::setTestNow();
});
