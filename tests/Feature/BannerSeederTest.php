<?php

use App\Models\Banner;
use Database\Seeders\BannerSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('banner seeder populates exactly 7 scandinavian furniture banners', function () {
    $this->seed(BannerSeeder::class);

    expect(Banner::count())->toBe(7);
});

test('banner seeder correctly distributes banners across expected positions', function () {
    $this->seed(BannerSeeder::class);

    $heroBanners = Banner::where('position', Banner::POSITION_HERO_SLIDER)->get();
    $promoBanners = Banner::where('position', Banner::POSITION_HOME_PROMO_2COL)->get();
    $collectionBanners = Banner::where('position', Banner::POSITION_HOME_COLLECTION_3COL)->get();

    expect($heroBanners)->toHaveCount(2)
        ->and($promoBanners)->toHaveCount(2)
        ->and($collectionBanners)->toHaveCount(3);
});

test('banner seeder populates hero slider banners with correct attributes and sort orders', function () {
    $this->seed(BannerSeeder::class);

    $heroBanners = Banner::position(Banner::POSITION_HERO_SLIDER)->active()->ordered()->get();

    expect($heroBanners)->toHaveCount(2);

    $slide1 = $heroBanners->first();
    expect($slide1->title)->toBe('Bộ Sưu Tập Bắc Âu 2026')
        ->and($slide1->eyebrow)->toBe('SCANDINAVIAN MINIMALISM')
        ->and($slide1->subtitle)->toContain('nội thất gỗ sồi tự nhiên')
        ->and($slide1->cta_text)->toBe('Khám Phá Ngay')
        ->and($slide1->link)->toBe('/catalog?category=living-room')
        ->and($slide1->image)->toBe('https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600&auto=format&fit=crop&q=80')
        ->and($slide1->image_url)->toBe('https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600&auto=format&fit=crop&q=80')
        ->and($slide1->open_in_new_tab)->toBeFalse()
        ->and($slide1->sort_order)->toBe(1)
        ->and($slide1->status)->toBe('active')
        ->and($slide1->clicks_count)->toBe(0);

    $slide2 = $heroBanners->last();
    expect($slide2->title)->toBe('Đèn Thả & Ghế Thư Giãn Tinh Tế')
        ->and($slide2->eyebrow)->toBe('PREMIUM LIGHTING & SEATING')
        ->and($slide2->subtitle)->toContain('Ánh sáng dịu nhẹ')
        ->and($slide2->cta_text)->toBe('Xem Bộ Sưu Tập')
        ->and($slide2->link)->toBe('/catalog?category=lighting')
        ->and($slide2->image)->toBe('https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=1600&auto=format&fit=crop&q=80')
        ->and($slide2->open_in_new_tab)->toBeFalse()
        ->and($slide2->sort_order)->toBe(2)
        ->and($slide2->status)->toBe('active')
        ->and($slide2->clicks_count)->toBe(0);
});

test('banner seeder populates home promo 2-col banners with correct attributes and sort orders', function () {
    $this->seed(BannerSeeder::class);

    $promoBanners = Banner::position(Banner::POSITION_HOME_PROMO_2COL)->active()->ordered()->get();

    expect($promoBanners)->toHaveCount(2);

    $promo1 = $promoBanners->first();
    expect($promo1->title)->toBe('Ưu Đãi Mùa Hè 20%')
        ->and($promo1->eyebrow)->toBe('SUMMER COLLECTION · BÀN ĂN')
        ->and($promo1->subtitle)->toContain('Giảm giá đặc biệt')
        ->and($promo1->cta_text)->toBe('Khám Phá · SHOP NOW')
        ->and($promo1->link)->toBe('/catalog?category=dining-room')
        ->and($promo1->image)->toBe('https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&auto=format&fit=crop&q=80')
        ->and($promo1->sort_order)->toBe(1)
        ->and($promo1->status)->toBe('active');

    $promo2 = $promoBanners->last();
    expect($promo2->title)->toBe('Ghế Thư Giãn Cao Cấp')
        ->and($promo2->eyebrow)->toBe('PREMIUM SEATING · PHÒNG KHÁCH')
        ->and($promo2->subtitle)->toContain('Nâng tầm không gian sống')
        ->and($promo2->cta_text)->toBe('Xem Ngay · SEE MORE')
        ->and($promo2->link)->toBe('/catalog?category=living-room')
        ->and($promo2->image)->toBe('https://images.unsplash.com/photo-1563861826100-9cb868fdbe1c?w=800&auto=format&fit=crop&q=80')
        ->and($promo2->sort_order)->toBe(2)
        ->and($promo2->status)->toBe('active');
});

test('banner seeder populates home collection 3-col banners with correct attributes and sort orders', function () {
    $this->seed(BannerSeeder::class);

    $collectionBanners = Banner::position(Banner::POSITION_HOME_COLLECTION_3COL)->active()->ordered()->get();

    expect($collectionBanners)->toHaveCount(3);

    $expected = [
        [
            'title' => 'Đồ Nội Thất Phòng Khách',
            'eyebrow' => 'CURATED · LIVING ROOM',
            'cta_text' => 'SEE COLLECTIONS',
            'link' => '/catalog?category=living-room',
            'image' => 'https://images.unsplash.com/photo-1540932239986-30128078f3c5?w=800&auto=format&fit=crop&q=80',
            'sort_order' => 1,
        ],
        [
            'title' => 'Trang Trí & Ánh Sáng',
            'eyebrow' => 'CURATED · LIGHTING',
            'cta_text' => 'SEE COLLECTIONS',
            'link' => '/catalog?category=lighting',
            'image' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?w=800&auto=format&fit=crop&q=80',
            'sort_order' => 2,
        ],
        [
            'title' => 'Phụ Kiện Nghệ Thuật',
            'eyebrow' => 'CURATED · ACCESSORIES',
            'cta_text' => 'SEE COLLECTIONS',
            'link' => '/catalog?category=accessories',
            'image' => 'https://images.unsplash.com/photo-1534349762230-e0cadf78f5da?w=800&auto=format&fit=crop&q=80',
            'sort_order' => 3,
        ],
    ];

    foreach ($expected as $index => $item) {
        $banner = $collectionBanners->get($index);
        expect($banner)->not->toBeNull()
            ->and($banner->title)->toBe($item['title'])
            ->and($banner->eyebrow)->toBe($item['eyebrow'])
            ->and($banner->cta_text)->toBe($item['cta_text'])
            ->and($banner->link)->toBe($item['link'])
            ->and($banner->image)->toBe($item['image'])
            ->and($banner->sort_order)->toBe($item['sort_order'])
            ->and($banner->status)->toBe('active');
    }
});

test('banner seeder is idempotent and produces exactly 7 banners when executed multiple times', function () {
    // First run
    $this->seed(BannerSeeder::class);
    expect(Banner::count())->toBe(7);

    // Second run
    $this->seed(BannerSeeder::class);
    expect(Banner::count())->toBe(7);

    // Third run
    $this->seed(BannerSeeder::class);
    expect(Banner::count())->toBe(7);

    // Ensure all positions retain expected counts
    expect(Banner::where('position', Banner::POSITION_HERO_SLIDER)->count())->toBe(2)
        ->and(Banner::where('position', Banner::POSITION_HOME_PROMO_2COL)->count())->toBe(2)
        ->and(Banner::where('position', Banner::POSITION_HOME_COLLECTION_3COL)->count())->toBe(3);
});

test('database seeder executes complete application seed including banner seeder', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Banner::count())->toBe(7);

    $heroSlides = Banner::position(Banner::POSITION_HERO_SLIDER)->active()->ordered()->get();
    expect($heroSlides)->toHaveCount(2);

    $promoBanners = Banner::position(Banner::POSITION_HOME_PROMO_2COL)->active()->ordered()->get();
    expect($promoBanners)->toHaveCount(2);

    $collectionBanners = Banner::position(Banner::POSITION_HOME_COLLECTION_3COL)->active()->ordered()->get();
    expect($collectionBanners)->toHaveCount(3);
});
