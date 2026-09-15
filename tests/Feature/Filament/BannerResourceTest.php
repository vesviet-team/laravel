<?php

use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $permissions = [
        'view_any_banner',
        'view_banner',
        'create_banner',
        'update_banner',
        'delete_banner',
        'delete_any_banner',
        'reorder_banner',
    ];

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);
});

test('can render banner list page and see records in table', function () {
    $banner = Banner::create([
        'title' => 'Summer Living Room Collection 2026',
        'position' => Banner::POSITION_HERO_SLIDER,
        'eyebrow' => 'BỘ SƯU TẬP MÙA HÈ',
        'subtitle' => 'Nội thất phòng khách phong cách Bắc Âu',
        'image' => 'banners/hero-1.jpg',
        'link' => '/products',
        'cta_text' => 'Khám Phá Ngay',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    Livewire::test(ListBanners::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$banner]);
});

test('can filter banners by position tabs', function () {
    $heroBanner = Banner::create([
        'title' => 'Hero Slide Banner',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/hero.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $promoBanner = Banner::create([
        'title' => 'Promo 2 Column Banner',
        'position' => Banner::POSITION_HOME_PROMO_2COL,
        'image' => 'banners/promo.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $collectionBanner = Banner::create([
        'title' => 'Collection 3 Column Banner',
        'position' => Banner::POSITION_HOME_COLLECTION_3COL,
        'image' => 'banners/collection.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $catalogBanner = Banner::create([
        'title' => 'Catalog Header Banner',
        'position' => Banner::POSITION_CATALOG_HEADER,
        'image' => 'banners/catalog.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $blogBanner = Banner::create([
        'title' => 'Blog Sidebar Banner',
        'position' => Banner::POSITION_BLOG_SIDEBAR,
        'image' => 'banners/blog.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $announcementBanner = Banner::create([
        'title' => 'Top Announcement Banner',
        'position' => Banner::POSITION_TOP_ANNOUNCEMENT,
        'image' => 'banners/announcement.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    // Tab 'all' sees all
    Livewire::test(ListBanners::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$heroBanner, $promoBanner, $collectionBanner, $catalogBanner, $blogBanner, $announcementBanner]);

    // Active tab hero_slider
    Livewire::test(ListBanners::class)
        ->set('activeTab', 'hero_slider')
        ->assertCanSeeTableRecords([$heroBanner])
        ->assertCanNotSeeTableRecords([$promoBanner, $collectionBanner, $catalogBanner, $blogBanner, $announcementBanner]);

    // Active tab home_promo_2col
    Livewire::test(ListBanners::class)
        ->set('activeTab', 'home_promo_2col')
        ->assertCanSeeTableRecords([$promoBanner])
        ->assertCanNotSeeTableRecords([$heroBanner, $collectionBanner, $catalogBanner, $blogBanner, $announcementBanner]);

    // Active tab home_collection_3col
    Livewire::test(ListBanners::class)
        ->set('activeTab', 'home_collection_3col')
        ->assertCanSeeTableRecords([$collectionBanner])
        ->assertCanNotSeeTableRecords([$heroBanner, $promoBanner, $catalogBanner, $blogBanner, $announcementBanner]);

    // Active tab catalog_header
    Livewire::test(ListBanners::class)
        ->set('activeTab', 'catalog_header')
        ->assertCanSeeTableRecords([$catalogBanner])
        ->assertCanNotSeeTableRecords([$heroBanner, $promoBanner, $collectionBanner, $blogBanner, $announcementBanner]);

    // Active tab blog_sidebar
    Livewire::test(ListBanners::class)
        ->set('activeTab', 'blog_sidebar')
        ->assertCanSeeTableRecords([$blogBanner])
        ->assertCanNotSeeTableRecords([$heroBanner, $promoBanner, $collectionBanner, $catalogBanner, $announcementBanner]);

    // Active tab top_announcement
    Livewire::test(ListBanners::class)
        ->set('activeTab', 'top_announcement')
        ->assertCanSeeTableRecords([$announcementBanner])
        ->assertCanNotSeeTableRecords([$heroBanner, $promoBanner, $collectionBanner, $catalogBanner, $blogBanner]);
});

test('can create a banner with all dynamic fields via filament form', function () {
    Storage::fake('public');
    $file = UploadedFile::fake()->create('banner.jpg', 500, 'image/jpeg');

    Livewire::test(CreateBanner::class)
        ->fillForm([
            'title' => 'Khuyến Mãi Sofa Mùa Hè',
            'position' => Banner::POSITION_HERO_SLIDER,
            'eyebrow' => 'BỘ SƯU TẬP 2026',
            'subtitle' => 'Giảm giá tới 30% cho các sản phẩm sofa da cao cấp.',
            'cta_text' => 'Mua Ngay',
            'link' => '/category/sofa-phong-khach',
            'open_in_new_tab' => true,
            'image' => $file,
            'status' => 'active',
            'sort_order' => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('banners', [
        'title' => 'Khuyến Mãi Sofa Mùa Hè',
        'position' => Banner::POSITION_HERO_SLIDER,
        'eyebrow' => 'BỘ SƯU TẬP 2026',
        'subtitle' => 'Giảm giá tới 30% cho các sản phẩm sofa da cao cấp.',
        'cta_text' => 'Mua Ngay',
        'link' => '/category/sofa-phong-khach',
        'open_in_new_tab' => true,
        'status' => 'active',
        'sort_order' => 5,
    ]);
});

test('validates required fields on create banner form', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm([
            'title' => null,
            'image' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'title' => 'required',
            'image' => 'required',
        ]);
});

test('can edit an existing banner and update position and schedule', function () {
    $banner = Banner::create([
        'title' => 'Banner Cũ',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/old.jpg',
        'status' => 'inactive',
        'sort_order' => 0,
    ]);

    $startsAt = now()->addDays(1)->format('Y-m-d H:i:s');
    $endsAt = now()->addDays(10)->format('Y-m-d H:i:s');

    Livewire::test(EditBanner::class, ['record' => $banner->getKey()])
        ->fillForm([
            'title' => 'Banner Mới Đã Sửa',
            'position' => Banner::POSITION_HOME_PROMO_2COL,
            'status' => 'active',
            'sort_order' => 10,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $banner->refresh();
    expect($banner->title)->toBe('Banner Mới Đã Sửa');
    expect($banner->position)->toBe(Banner::POSITION_HOME_PROMO_2COL);
    expect($banner->status)->toBe('active');
    expect($banner->sort_order)->toBe(10);
    expect($banner->starts_at)->not->toBeNull();
    expect($banner->ends_at)->not->toBeNull();
});

test('can reorder banners using table reordering', function () {
    $banner1 = Banner::create([
        'title' => 'Slide 1',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/1.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $banner2 = Banner::create([
        'title' => 'Slide 2',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/2.jpg',
        'status' => 'active',
        'sort_order' => 2,
    ]);

    Livewire::test(ListBanners::class)
        ->call('reorderTable', [$banner2->id, $banner1->id]);

    $banner1->refresh();
    $banner2->refresh();

    expect($banner2->sort_order)->toBe(1);
    expect($banner1->sort_order)->toBe(2);
});

test('can filter table records by position and status select filters', function () {
    $activeHero = Banner::create([
        'title' => 'Active Hero',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/1.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $inactiveHero = Banner::create([
        'title' => 'Inactive Hero',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/2.jpg',
        'status' => 'inactive',
        'sort_order' => 2,
    ]);

    $activePromo = Banner::create([
        'title' => 'Active Promo',
        'position' => Banner::POSITION_HOME_PROMO_2COL,
        'image' => 'banners/3.jpg',
        'status' => 'active',
        'sort_order' => 3,
    ]);

    Livewire::test(ListBanners::class)
        ->filterTable('position', Banner::POSITION_HERO_SLIDER)
        ->assertCanSeeTableRecords([$activeHero, $inactiveHero])
        ->assertCanNotSeeTableRecords([$activePromo]);

    Livewire::test(ListBanners::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$activeHero, $activePromo])
        ->assertCanNotSeeTableRecords([$inactiveHero]);
});

test('can delete a banner via table delete action', function () {
    $banner = Banner::create([
        'title' => 'Banner To Delete',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/delete.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    Livewire::test(ListBanners::class)
        ->callTableAction('delete', $banner)
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseMissing('banners', [
        'id' => $banner->id,
    ]);
});

test('can bulk delete banners via bulk action', function () {
    $banner1 = Banner::create([
        'title' => 'Bulk Delete 1',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/bulk1.jpg',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $banner2 = Banner::create([
        'title' => 'Bulk Delete 2',
        'position' => Banner::POSITION_HERO_SLIDER,
        'image' => 'banners/bulk2.jpg',
        'status' => 'active',
        'sort_order' => 2,
    ]);

    Livewire::test(ListBanners::class)
        ->callTableBulkAction('delete', [$banner1, $banner2])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseMissing('banners', ['id' => $banner1->id]);
    $this->assertDatabaseMissing('banners', ['id' => $banner2->id]);
});
