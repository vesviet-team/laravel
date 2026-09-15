<?php

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('banner constants and positions dictionary are correctly configured', function () {
    expect(Banner::POSITION_HERO_SLIDER)->toBe('hero_slider');
    expect(Banner::POSITION_HOME_PROMO_2COL)->toBe('home_promo_2col');
    expect(Banner::POSITION_HOME_COLLECTION_3COL)->toBe('home_collection_3col');
    expect(Banner::POSITION_CATALOG_HEADER)->toBe('catalog_header');
    expect(Banner::POSITION_BLOG_SIDEBAR)->toBe('blog_sidebar');
    expect(Banner::POSITION_TOP_ANNOUNCEMENT)->toBe('top_announcement');

    expect(Banner::POSITIONS)->toBeArray()->toHaveCount(6);
    expect(Banner::POSITIONS)->toHaveKeys([
        Banner::POSITION_HERO_SLIDER,
        Banner::POSITION_HOME_PROMO_2COL,
        Banner::POSITION_HOME_COLLECTION_3COL,
        Banner::POSITION_CATALOG_HEADER,
        Banner::POSITION_BLOG_SIDEBAR,
        Banner::POSITION_TOP_ANNOUNCEMENT,
    ]);
});

test('can create and persist banner with full fillable attributes and casts', function () {
    $now = Carbon::now()->startOfSecond();
    $startsAt = (clone $now)->subHour();
    $endsAt = (clone $now)->addDays(7);

    $banner = Banner::create([
        'position' => Banner::POSITION_HERO_SLIDER,
        'title' => 'Bộ Sưu Tập Bắc Âu 2026',
        'eyebrow' => 'SCANDINAVIAN MINIMALISM',
        'subtitle' => 'Tinh hoa nội thất gỗ sồi tự nhiên cho không gian sống hiện đại.',
        'image' => 'banners/hero-nordic.webp',
        'link' => '/catalog?category=living-room',
        'cta_text' => 'Khám Phá Ngay',
        'open_in_new_tab' => 1,
        'status' => 'active',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'sort_order' => '5',
        'clicks_count' => '42',
    ]);

    expect($banner->exists)->toBeTrue();
    expect($banner->id)->toBeGreaterThan(0);
    expect($banner->position)->toBe(Banner::POSITION_HERO_SLIDER);
    expect($banner->title)->toBe('Bộ Sưu Tập Bắc Âu 2026');
    expect($banner->eyebrow)->toBe('SCANDINAVIAN MINIMALISM');
    expect($banner->subtitle)->toBe('Tinh hoa nội thất gỗ sồi tự nhiên cho không gian sống hiện đại.');
    expect($banner->image)->toBe('banners/hero-nordic.webp');
    expect($banner->link)->toBe('/catalog?category=living-room');
    expect($banner->cta_text)->toBe('Khám Phá Ngay');

    // Cast assertions
    expect($banner->open_in_new_tab)->toBeBool()->toBeTrue();
    expect($banner->sort_order)->toBeInt()->toBe(5);
    expect($banner->clicks_count)->toBeInt()->toBe(42);
    expect($banner->starts_at)->toBeInstanceOf(Carbon::class);
    expect($banner->ends_at)->toBeInstanceOf(Carbon::class);
    expect($banner->starts_at->toDateTimeString())->toBe($startsAt->toDateTimeString());
    expect($banner->ends_at->toDateTimeString())->toBe($endsAt->toDateTimeString());

    $this->assertDatabaseHas('banners', [
        'id' => $banner->id,
        'position' => 'hero_slider',
        'title' => 'Bộ Sưu Tập Bắc Âu 2026',
        'sort_order' => 5,
        'clicks_count' => 42,
    ]);
});

test('scopeActive accurately filters banners by status and scheduling time window', function () {
    Carbon::setTestNow('2026-08-20 12:00:00');

    // 1. Active with no scheduling boundaries (Always Active)
    $activeIndefinite = Banner::create([
        'title' => 'Active Indefinite',
        'image' => 'banners/1.jpg',
        'status' => 'active',
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // 2. Active with past start date and null end date (Currently Running)
    $activePastStart = Banner::create([
        'title' => 'Active Past Start',
        'image' => 'banners/2.jpg',
        'status' => 'active',
        'starts_at' => Carbon::parse('2026-08-15 00:00:00'),
        'ends_at' => null,
    ]);

    // 3. Active with null start date and future end date (Currently Running)
    $activeFutureEnd = Banner::create([
        'title' => 'Active Future End',
        'image' => 'banners/3.jpg',
        'status' => 'active',
        'starts_at' => null,
        'ends_at' => Carbon::parse('2026-08-25 00:00:00'),
    ]);

    // 4. Active with valid bounded window covering current time
    $activeWindow = Banner::create([
        'title' => 'Active Window',
        'image' => 'banners/4.jpg',
        'status' => 'active',
        'starts_at' => Carbon::parse('2026-08-19 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-21 00:00:00'),
    ]);

    // 5. Inactive status with null dates (Should be excluded)
    $inactive = Banner::create([
        'title' => 'Inactive Banner',
        'image' => 'banners/5.jpg',
        'status' => 'inactive',
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // 6. Inactive status within valid time window (Should be excluded)
    $inactiveInWindow = Banner::create([
        'title' => 'Inactive In Window',
        'image' => 'banners/6.jpg',
        'status' => 'inactive',
        'starts_at' => Carbon::parse('2026-08-19 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-21 00:00:00'),
    ]);

    // 7. Active status but scheduled for the future (Should be excluded)
    $futureScheduled = Banner::create([
        'title' => 'Future Scheduled',
        'image' => 'banners/7.jpg',
        'status' => 'active',
        'starts_at' => Carbon::parse('2026-08-21 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-30 00:00:00'),
    ]);

    // 8. Active status but already expired in past (Should be excluded)
    $expired = Banner::create([
        'title' => 'Expired Banner',
        'image' => 'banners/8.jpg',
        'status' => 'active',
        'starts_at' => Carbon::parse('2026-08-01 00:00:00'),
        'ends_at' => Carbon::parse('2026-08-19 23:59:59'),
    ]);

    $activeBanners = Banner::active()->get();

    expect($activeBanners)->toHaveCount(4);
    $activeIds = $activeBanners->pluck('id')->all();

    expect($activeIds)->toContain(
        $activeIndefinite->id,
        $activePastStart->id,
        $activeFutureEnd->id,
        $activeWindow->id
    );

    expect($activeIds)->not->toContain(
        $inactive->id,
        $inactiveInWindow->id,
        $futureScheduled->id,
        $expired->id
    );

    Carbon::setTestNow();
});

test('scopePosition filters banners by exact position', function () {
    $hero = Banner::create([
        'position' => Banner::POSITION_HERO_SLIDER,
        'title' => 'Hero 1',
        'image' => 'banners/hero1.jpg',
    ]);

    $promo = Banner::create([
        'position' => Banner::POSITION_HOME_PROMO_2COL,
        'title' => 'Promo 1',
        'image' => 'banners/promo1.jpg',
    ]);

    $collection = Banner::create([
        'position' => Banner::POSITION_HOME_COLLECTION_3COL,
        'title' => 'Collection 1',
        'image' => 'banners/col1.jpg',
    ]);

    $heroResults = Banner::position(Banner::POSITION_HERO_SLIDER)->get();
    expect($heroResults)->toHaveCount(1);
    expect($heroResults->first()->id)->toBe($hero->id);

    $promoResults = Banner::position(Banner::POSITION_HOME_PROMO_2COL)->get();
    expect($promoResults)->toHaveCount(1);
    expect($promoResults->first()->id)->toBe($promo->id);

    $colResults = Banner::position(Banner::POSITION_HOME_COLLECTION_3COL)->get();
    expect($colResults)->toHaveCount(1);
    expect($colResults->first()->id)->toBe($collection->id);
});

test('scopeOrdered sorts banners by sort_order ascending', function () {
    $banner3 = Banner::create([
        'title' => 'Third',
        'image' => 'banners/3.jpg',
        'sort_order' => 30,
    ]);

    $banner1 = Banner::create([
        'title' => 'First',
        'image' => 'banners/1.jpg',
        'sort_order' => 10,
    ]);

    $banner2 = Banner::create([
        'title' => 'Second',
        'image' => 'banners/2.jpg',
        'sort_order' => 20,
    ]);

    $ordered = Banner::ordered()->get();
    expect($ordered->pluck('id')->all())->toBe([
        $banner1->id,
        $banner2->id,
        $banner3->id,
    ]);
});

test('recordClick atomically increments clicks_count and bypasses observer cache flushing', function () {
    Cache::put('home_banners', ['mocked_cached_data'], 3600);
    expect(Cache::has('home_banners'))->toBeTrue();

    $banner = Banner::create([
        'title' => 'Clickable Banner',
        'image' => 'banners/click.jpg',
        'clicks_count' => 0,
    ]);

    // Cache was flushed on creation due to BannerObserver@created
    Cache::put('home_banners', ['mocked_cached_data'], 3600);
    expect(Cache::has('home_banners'))->toBeTrue();

    // Call atomic recordClick
    $result = $banner->recordClick();
    expect($result)->toBeTrue();

    // Reload from database to verify persistence
    $banner->refresh();
    expect($banner->clicks_count)->toBe(1);

    // Verify cache was NOT flushed by recordClick
    expect(Cache::has('home_banners'))->toBeTrue();

    // Call recordClick again
    $banner->recordClick();
    $banner->refresh();
    expect($banner->clicks_count)->toBe(2);
    expect(Cache::has('home_banners'))->toBeTrue();

    // Contrast with standard update which triggers observer
    $banner->update(['title' => 'Modified Title']);
    expect(Cache::has('home_banners'))->toBeFalse();
});

test('getImageUrlAttribute handles absolute URLs, local disk paths, and empty states', function () {
    // 1. Absolute HTTPS URL
    $externalBanner = new Banner([
        'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600',
    ]);
    expect($externalBanner->image_url)->toBe('https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600');

    // 2. Absolute HTTP URL
    $httpBanner = new Banner([
        'image' => 'http://cdn.example.com/banners/promo.png',
    ]);
    expect($httpBanner->image_url)->toBe('http://cdn.example.com/banners/promo.png');

    // 3. Local relative storage path
    $localBanner = new Banner([
        'image' => 'banners/hero-nordic.webp',
    ]);
    $expectedStorageUrl = Storage::disk('public')->url('banners/hero-nordic.webp');
    expect($localBanner->image_url)->toBe($expectedStorageUrl);

    // 4. Empty / null image
    $emptyBanner = new Banner(['image' => null]);
    expect($emptyBanner->image_url)->toBe('');

    $blankBanner = new Banner(['image' => '']);
    expect($blankBanner->image_url)->toBe('');
});

test('BannerFactory generates models with all states', function () {
    $hero = Banner::factory()->hero()->active()->create();
    expect($hero->position)->toBe(Banner::POSITION_HERO_SLIDER);
    expect($hero->status)->toBe('active');
    expect($hero->starts_at)->toBeNull();
    expect($hero->ends_at)->toBeNull();

    $promo = Banner::factory()->promo2Col()->inactive()->create();
    expect($promo->position)->toBe(Banner::POSITION_HOME_PROMO_2COL);
    expect($promo->status)->toBe('inactive');

    $collection = Banner::factory()->collection3Col()->create();
    expect($collection->position)->toBe(Banner::POSITION_HOME_COLLECTION_3COL);

    $catalog = Banner::factory()->catalogHeader()->create();
    expect($catalog->position)->toBe(Banner::POSITION_CATALOG_HEADER);

    $sidebar = Banner::factory()->blogSidebar()->create();
    expect($sidebar->position)->toBe(Banner::POSITION_BLOG_SIDEBAR);

    $announcement = Banner::factory()->topAnnouncement()->create();
    expect($announcement->position)->toBe(Banner::POSITION_TOP_ANNOUNCEMENT);

    $future = Banner::factory()->scheduledFuture()->create();
    expect($future->starts_at)->toBeInstanceOf(Carbon::class);
    expect($future->starts_at->isFuture())->toBeTrue();

    $expired = Banner::factory()->expired()->create();
    expect($expired->ends_at)->toBeInstanceOf(Carbon::class);
    expect($expired->ends_at->isPast())->toBeTrue();
});
