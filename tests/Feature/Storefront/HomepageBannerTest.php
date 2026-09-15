<?php

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('homepage renders active hero slider banners from database', function () {
    $hero1 = Banner::factory()->hero()->active()->create([
        'title' => 'Bộ Sưu Tập Bắc Âu 2026',
        'eyebrow' => 'SCANDINAVIAN MINIMALISM',
        'subtitle' => 'Nội thất gỗ sồi cao cấp tinh tế.',
        'cta_text' => 'Khám Phá BST',
        'open_in_new_tab' => false,
        'sort_order' => 1,
    ]);

    $hero2 = Banner::factory()->hero()->active()->create([
        'title' => 'Ghế Thư Giãn Hiện Đại',
        'eyebrow' => 'NEW ARRIVALS',
        'subtitle' => 'Chất liệu nỉ cao cấp nhập khẩu Đan Mạch.',
        'cta_text' => 'Xem Chi Tiết',
        'open_in_new_tab' => true,
        'sort_order' => 2,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewHas('heroSlides', function ($slides) use ($hero1, $hero2) {
        return $slides->count() === 2
            && $slides->first()->id === $hero1->id
            && $slides->last()->id === $hero2->id;
    });

    $content = $response->getContent();
    expect($content)->toContain('Bộ Sưu Tập Bắc Âu 2026')
        ->toContain('SCANDINAVIAN MINIMALISM')
        ->toContain('Khám Phá BST')
        ->toContain('Ghế Thư Giãn Hiện Đại')
        ->toContain(route('banner.click', $hero1->id))
        ->toContain(route('banner.click', $hero2->id));
});

test('homepage renders active 2-column promo banners from database', function () {
    $promo1 = Banner::factory()->promo2Col()->active()->create([
        'title' => 'Đèn Thả Bắc Âu',
        'eyebrow' => 'LIGHTING COLLECTION',
        'subtitle' => 'Giảm 20% cho toàn bộ đèn trang trí.',
        'cta_text' => 'Mua Ngay',
        'open_in_new_tab' => false,
        'sort_order' => 1,
    ]);

    $promo2 = Banner::factory()->promo2Col()->active()->create([
        'title' => 'Bàn Ăn Gỗ Tự Nhiên',
        'eyebrow' => 'DINING ROOM',
        'subtitle' => 'Thiết kế tối giản cho không gian ấm cúng.',
        'cta_text' => 'Xem Bàn Ăn',
        'open_in_new_tab' => true,
        'sort_order' => 2,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewHas('promoBanners', function ($promos) use ($promo1, $promo2) {
        return $promos->count() === 2
            && $promos->first()->id === $promo1->id
            && $promos->last()->id === $promo2->id;
    });

    $content = $response->getContent();
    expect($content)->toContain('Đèn Thả Bắc Âu')
        ->toContain('LIGHTING COLLECTION')
        ->toContain('Bàn Ăn Gỗ Tự Nhiên')
        ->toContain('DINING ROOM')
        ->toContain(route('banner.click', $promo1->id))
        ->toContain(route('banner.click', $promo2->id));
});

test('homepage renders active 3-column collection banners from database', function () {
    $col1 = Banner::factory()->collection3Col()->active()->create([
        'title' => 'Phòng Khách Tối Giản',
        'eyebrow' => 'LIVING ROOM 01',
        'subtitle' => 'Không gian mở thanh lịch.',
        'cta_text' => 'XEM PHÒNG KHÁCH',
        'sort_order' => 1,
    ]);

    $col2 = Banner::factory()->collection3Col()->active()->create([
        'title' => 'Phòng Ngủ Ấm Cúng',
        'eyebrow' => 'BEDROOM 02',
        'subtitle' => 'Giấc ngủ trọn vẹn phong cách Nordic.',
        'cta_text' => 'XEM PHÒNG NGỦ',
        'sort_order' => 2,
    ]);

    $col3 = Banner::factory()->collection3Col()->active()->create([
        'title' => 'Phòng Làm Việc Copenhague',
        'eyebrow' => 'WORKSPACE 03',
        'subtitle' => 'Gọn gàng và tràn đầy cảm hứng.',
        'cta_text' => 'XEM WORKSPACE',
        'sort_order' => 3,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewHas('collectionBanners', function ($collections) use ($col1, $col2, $col3) {
        return $collections->count() === 3
            && $collections->pluck('id')->all() === [$col1->id, $col2->id, $col3->id];
    });

    $content = $response->getContent();
    expect($content)->toContain('Phòng Khách Tối Giản')
        ->toContain('Phòng Ngủ Ấm Cúng')
        ->toContain('Phòng Làm Việc Copenhague')
        ->toContain(route('banner.click', $col1->id))
        ->toContain(route('banner.click', $col2->id))
        ->toContain(route('banner.click', $col3->id));
});

test('smart hybrid fallback: homepage renders default hero slides when database has 0 hero banners', function () {
    expect(Banner::count())->toBe(0);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewHas('heroSlides', function ($slides) {
        return $slides->isEmpty();
    });

    $content = $response->getContent();
    // Default Scandinavian Fallback Content
    expect($content)->toContain('Bộ Sưu Tập Mới')
        ->toContain('Thiết Kế Hiện Đại')
        ->toContain('Ends Today')
        ->toContain('New Arrivals')
        ->toContain('Phong cách tối giản, tinh tế')
        ->toContain('Chất liệu cao cấp');
});

test('smart hybrid fallback: homepage renders default promo banners when database has 0 promo banners', function () {
    expect(Banner::count())->toBe(0);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewHas('promoBanners', function ($promos) {
        return $promos->isEmpty();
    });

    $content = $response->getContent();
    // Default 2-Col Promo Fallback Content
    expect($content)->toContain('Lighting on Express · Bộ Sưu Tập')
        ->toContain('Phong Cách Mùa Này')
        ->toContain('Dining Chairs · Chất Liệu Cao Cấp')
        ->toContain('Thiết Kế Tối Giản');
});

test('smart hybrid fallback: homepage renders default collection banners when database has 0 collection banners', function () {
    expect(Banner::count())->toBe(0);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewHas('collectionBanners', function ($collections) {
        return $collections->isEmpty();
    });

    $content = $response->getContent();
    // Default 3-Col Collection Fallback Content
    expect($content)->toContain('Copenhague Desk · Danh Mục 01')
        ->toContain('Đồ Nội Thất')
        ->toContain('Cement Wood Lamp · Danh Mục 02')
        ->toContain('Trang Trí Nhà')
        ->toContain('Arte 60 Stool · Danh Mục 03')
        ->toContain('Phụ Kiện');
});

test('homepage ignores inactive, future-scheduled, and expired banners', function () {
    Carbon::setTestNow('2026-08-20 12:00:00');

    $activeHero = Banner::factory()->hero()->active()->create([
        'title' => 'Active Running Hero',
    ]);

    $inactiveHero = Banner::factory()->hero()->inactive()->create([
        'title' => 'Inactive Hidden Hero',
    ]);

    $futureHero = Banner::factory()->hero()->scheduledFuture()->create([
        'title' => 'Future Upcoming Hero',
    ]);

    $expiredHero = Banner::factory()->hero()->expired()->create([
        'title' => 'Expired Past Hero',
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewHas('heroSlides', function ($slides) use ($activeHero) {
        return $slides->count() === 1 && $slides->first()->id === $activeHero->id;
    });

    $content = $response->getContent();
    expect($content)->toContain('Active Running Hero')
        ->not->toContain('Inactive Hidden Hero')
        ->not->toContain('Future Upcoming Hero')
        ->not->toContain('Expired Past Hero');

    Carbon::setTestNow();
});

test('homepage respects sort_order ordering for all banner positions', function () {
    $bannerB = Banner::factory()->hero()->active()->create([
        'title' => 'Banner B (Sort 20)',
        'sort_order' => 20,
    ]);

    $bannerA = Banner::factory()->hero()->active()->create([
        'title' => 'Banner A (Sort 10)',
        'sort_order' => 10,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $heroSlides = $response->viewData('heroSlides');
    expect($heroSlides->pluck('id')->all())->toBe([$bannerA->id, $bannerB->id]);
});

test('homepage hero slider includes WCAG 2.2 carousel and slide accessibility attributes', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $content = $response->getContent();

    // SC 4.1.2 Name, Role, Value & E2E Invariant
    expect($content)->toContain('role="region"')
        ->toContain('aria-roledescription="carousel"')
        ->toContain('aria-label="Hero banner"')
        ->toContain('role="group"')
        ->toContain('aria-roledescription="slide"');

    // SC 2.2.2 Pause, Stop, Hide
    expect($content)->toContain('@mouseenter="pause()"')
        ->toContain('@mouseleave="resume()"')
        ->toContain('@focusin="pause()"')
        ->toContain('@focusout="resume()"');

    // SC 1.3.1 Info and Relationships (Tablist Navigation)
    expect($content)->toContain('role="tablist"')
        ->toContain('aria-label="Slide navigation"')
        ->toContain('role="tab"');

    // SC 2.5.8 Target Size & Controls
    expect($content)->toContain('aria-label="Slide trước"')
        ->toContain('aria-label="Slide tiếp theo"');
});

test('homepage banners link to click tracking route and open in new tab when configured', function () {
    $heroNewTab = Banner::factory()->hero()->active()->create([
        'title' => 'New Tab Hero Banner',
        'open_in_new_tab' => true,
    ]);

    $promoNewTab = Banner::factory()->promo2Col()->active()->create([
        'title' => 'New Tab Promo Banner',
        'open_in_new_tab' => true,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $content = $response->getContent();

    // Verify tracking URLs
    expect($content)->toContain(route('banner.click', $heroNewTab->id))
        ->toContain(route('banner.click', $promoNewTab->id))
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"');
});

test('homepage preserves exact 7-section sequence', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $html = $response->getContent();

    // Section 1: Hero Carousel
    $posHero = strpos($html, 'Hero banner');
    // Section 2: Promo Banners
    $posPromo = strpos($html, 'SECTION 2: 2-COLUMN PROMO BANNERS') !== false
        ? strpos($html, 'SECTION 2: 2-COLUMN PROMO BANNERS')
        : strpos($html, 'Lighting on Express');
    // Section 3: Intro
    $posIntro = strpos($html, 'Great Design In Your Home');
    // Section 4: Featured Products
    $posFeatured = strpos($html, 'Sản Phẩm Nổi Bật');
    // Section 5: New Arrivals
    $posNewArrivals = strpos($html, 'Sản Phẩm Mới');
    // Section 6: Featured Collections
    $posCollections = strpos($html, 'Curated Spaces');
    // Section 7: Trust Badges
    $posTrust = strpos($html, 'Miễn Phí Vận Chuyển');

    expect($posHero)->not->toBeFalse();
    expect($posPromo)->not->toBeFalse();
    expect($posIntro)->not->toBeFalse();
    expect($posFeatured)->not->toBeFalse();
    expect($posNewArrivals)->not->toBeFalse();
    expect($posCollections)->not->toBeFalse();
    expect($posTrust)->not->toBeFalse();

    // Assert sequential order
    expect($posHero)->toBeLessThan($posPromo);
    expect($posPromo)->toBeLessThan($posIntro);
    expect($posIntro)->toBeLessThan($posFeatured);
    expect($posFeatured)->toBeLessThan($posNewArrivals);
    expect($posNewArrivals)->toBeLessThan($posCollections);
    expect($posCollections)->toBeLessThan($posTrust);
});
