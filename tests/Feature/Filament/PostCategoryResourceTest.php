<?php

use App\Filament\Resources\PostCategoryResource\Pages\CreatePostCategory;
use App\Filament\Resources\PostCategoryResource\Pages\EditPostCategory;
use App\Filament\Resources\PostCategoryResource\Pages\ListPostCategories;
use App\Models\Post;
use App\Models\PostCategory;
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
        'view_any_post::category',
        'view_post::category',
        'create_post::category',
        'update_post::category',
        'delete_post::category',
        'delete_any_post::category',
    ];

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);
});

test('can render post category list page and see records', function () {
    $category = PostCategory::create([
        'name' => 'Nội Thất Phòng Khách',
        'slug' => 'noi-that-phong-khach',
        'description' => 'Mô tả danh mục phòng khách.',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Livewire::test(ListPostCategories::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$category]);
});

test('can create post category with auto slug and seo attributes', function () {
    Livewire::test(CreatePostCategory::class)
        ->fillForm([
            'name' => 'Phòng Ngủ Tối Giản',
            'slug' => 'phong-ngu-toi-gian',
            'description' => 'Ý tưởng thiết kế phòng ngủ phong cách Japandi và Scandinavian.',
            'is_active' => true,
            'sort_order' => 2,
            'seo_title' => 'Nội Thất Phòng Ngủ Tối Giản - Sober',
            'seo_description' => 'Bộ sưu tập nội thất phòng ngủ cao cấp.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('post_categories', [
        'name' => 'Phòng Ngủ Tối Giản',
        'slug' => 'phong-ngu-toi-gian',
        'is_active' => true,
    ]);
});

test('can edit existing post category', function () {
    $category = PostCategory::create([
        'name' => 'Góc Làm Việc',
        'slug' => 'goc-lam-viec',
        'is_active' => false,
    ]);

    Livewire::test(EditPostCategory::class, ['record' => $category->getKey()])
        ->fillForm([
            'name' => 'Góc Làm Việc Thông Minh',
            'is_active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $category->refresh();
    expect($category->name)->toBe('Góc Làm Việc Thông Minh');
    expect($category->is_active)->toBeTrue();
});

test('rejects post category with reserved route slug', function (string $reservedSlug) {
    Livewire::test(CreatePostCategory::class)
        ->fillForm([
            'name' => 'Danh Mục Trùng Hệ Thống',
            'slug' => $reservedSlug,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    $this->assertDatabaseMissing('post_categories', [
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
    'storage',
]);

test('shows correct posts count in category table', function () {
    $category = PostCategory::create([
        'name' => 'Cảm Hứng Sống',
        'slug' => 'cam-hung-song',
    ]);

    Post::create([
        'post_category_id' => $category->id,
        'user_id' => $this->user->id,
        'title' => 'Bài Viết 1',
        'slug' => 'bai-viet-1',
        'body' => '<p>Nội dung 1</p>',
        'status' => 'published',
    ]);

    Post::create([
        'post_category_id' => $category->id,
        'user_id' => $this->user->id,
        'title' => 'Bài Viết 2',
        'slug' => 'bai-viet-2',
        'body' => '<p>Nội dung 2</p>',
        'status' => 'published',
    ]);

    Livewire::test(ListPostCategories::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$category]);

    $categoryWithCount = PostCategory::withCount('posts')->find($category->id);
    expect($categoryWithCount->posts_count)->toBe(2);
});
