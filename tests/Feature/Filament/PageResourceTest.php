<?php

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $permissions = [
        'view_any_page',
        'view_page',
        'create_page',
        'update_page',
        'delete_page',
        'delete_any_page',
    ];

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);
});

test('can render page resource list and see static pages', function () {
    $page = Page::create([
        'title' => 'Về Chúng Tôi - Sober Furniture',
        'slug' => 've-chung-toi',
        'body' => '<p>Giới thiệu thương hiệu nội thất Sober.</p>',
        'template' => 'default',
        'is_published' => true,
    ]);

    Livewire::test(ListPages::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$page]);
});

test('can create a static page with template selection and seo fields', function () {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Chính Sách Bảo Hành & Bảo Trì',
            'slug' => 'chinh-sach-bao-hanh',
            'template' => 'policy',
            'body' => '<h2>Quy định bảo hành 10 năm khung gỗ</h2><p>Cam kết bảo hành chính hãng đối với tất cả sản phẩm nội thất.</p>',
            'is_published' => true,
            'seo_title' => 'Chính Sách Bảo Hành - Sober Furniture',
            'seo_description' => 'Chi tiết quy định bảo hành 10 năm cho nội thất cao cấp Sober.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('pages', [
        'title' => 'Chính Sách Bảo Hành & Bảo Trì',
        'slug' => 'chinh-sach-bao-hanh',
        'template' => 'policy',
        'is_published' => true,
    ]);
});

test('can edit an existing page and change template', function () {
    $page = Page::create([
        'title' => 'Chính Sách Đổi Trả',
        'slug' => 'chinh-sach-doi-tra',
        'template' => 'default',
        'body' => '<p>Nội dung đổi trả...</p>',
        'is_published' => true,
    ]);

    Livewire::test(EditPage::class, ['record' => $page->getKey()])
        ->fillForm([
            'template' => 'policy',
            'title' => 'Chính Sách Đổi Trả 30 Ngày',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();
    expect($page->template)->toBe('policy');
    expect($page->title)->toBe('Chính Sách Đổi Trả 30 Ngày');
});

test('rejects page creation with reserved system slug', function (string $reservedSlug) {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Trang Trùng Hệ Thống',
            'slug' => $reservedSlug,
            'template' => 'default',
            'body' => '<p>Nội dung.</p>',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    $this->assertDatabaseMissing('pages', [
        'slug' => $reservedSlug,
    ]);
})->with([
    'admin',
    'blog',
    'cart',
    'checkout',
    'products',
    'categories',
    'login',
    'register',
    'track-order',
    'shield',
]);

test('rejects duplicate page slug', function () {
    Page::create([
        'title' => 'Điều Khoản Dịch Vụ',
        'slug' => 'dieu-khoan-dich-vu',
        'body' => '<p>Nội dung gốc.</p>',
        'is_published' => true,
    ]);

    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Điều Khoản Trùng',
            'slug' => 'dieu-khoan-dich-vu',
            'template' => 'policy',
            'body' => '<p>Nội dung mới.</p>',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});
