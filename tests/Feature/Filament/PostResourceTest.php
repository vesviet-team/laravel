<?php

use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
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
        'view_any_post',
        'view_post',
        'create_post',
        'update_post',
        'delete_post',
        'delete_any_post',
    ];

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);
});

test('can render post resource list page and see records in table', function () {
    $category = PostCategory::create([
        'name' => 'Kiến Trúc & Nội Thất',
        'slug' => 'kien-truc-noi-that',
    ]);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $this->user->id,
        'title' => 'Xu Hướng Nội Thất Scandinavian 2026',
        'slug' => 'xu-huong-noi-that-scandinavian-2026',
        'excerpt' => 'Tóm tắt bài viết xu hướng.',
        'body' => '<p>Nội dung phong cách Bắc Âu với gỗ sồi và tông màu tối giản.</p>',
        'status' => 'published',
        'is_featured' => true,
    ]);

    Livewire::test(ListPosts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$post]);
});

test('can create a new post with contextual commerce product relations via filament form', function () {
    $category = PostCategory::create([
        'name' => 'Cẩm Nang Nội Thất',
        'slug' => 'cam-nang-noi-that',
    ]);

    $shopCategory = Category::create([
        'name' => 'Bàn Ăn',
        'slug' => 'ban-an',
    ]);

    $product1 = Product::create([
        'category_id' => $shopCategory->id,
        'name' => 'Bàn Ăn Gỗ Sồi Neva',
        'slug' => 'ban-an-go-soi-neva',
        'sku' => 'BA-NEVA-01',
        'price' => 12500000,
        'stock' => 15,
        'status' => 'published',
    ]);

    $product2 = Product::create([
        'category_id' => $shopCategory->id,
        'name' => 'Ghế Ăn Grace Bọc Da',
        'slug' => 'ghe-an-grace-boc-da',
        'sku' => 'GA-GRACE-02',
        'price' => 2800000,
        'stock' => 30,
        'status' => 'published',
    ]);

    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Top 5 Mẫu Bàn Ăn Gỗ Sồi Được Yêu Thích Nhất',
            'slug' => 'top-5-mau-ban-an-go-soi',
            'excerpt' => 'Khám phá các mẫu bàn ăn Bắc Âu cao cấp.',
            'body' => '<p>Giới thiệu chi tiết bàn ăn gỗ sồi tự nhiên tinh tế cho không gian căn hộ hiện đại.</p>',
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'status' => 'published',
            'is_featured' => true,
            'products' => [$product1->id, $product2->id],
            'seo_title' => 'Top 5 Mẫu Bàn Ăn Gỗ Sồi - Sober Furniture',
            'seo_description' => 'Hướng dẫn lựa chọn bàn ăn gỗ sồi sang trọng và bền đẹp.',
            'canonical_url' => 'https://soberfurniture.vn/blog/top-5-mau-ban-an-go-soi',
            'schema_type' => 'BlogPosting',
            'faq_schema' => [
                [
                    'question' => 'Bàn ăn gỗ sồi có bền không?',
                    'answer' => 'Gỗ sồi nhập khẩu Bắc Mỹ đã qua sấy chuẩn có độ bền trên 20 năm.',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('posts', [
        'title' => 'Top 5 Mẫu Bàn Ăn Gỗ Sồi Được Yêu Thích Nhất',
        'slug' => 'top-5-mau-ban-an-go-soi',
        'status' => 'published',
        'is_featured' => true,
    ]);

    $createdPost = Post::where('slug', 'top-5-mau-ban-an-go-soi')->first();
    expect($createdPost)->not->toBeNull();
    expect($createdPost->products)->toHaveCount(2);
    expect($createdPost->products->pluck('id')->all())->toContain($product1->id, $product2->id);
    expect($createdPost->faq_schema)->toBeArray();
    expect($createdPost->faq_schema[0]['question'])->toBe('Bàn ăn gỗ sồi có bền không?');
});

test('can edit an existing post and update product associations', function () {
    $category = PostCategory::create([
        'name' => 'Ý Tưởng Thiết Kế',
        'slug' => 'y-tuong-thiet-ke',
    ]);

    $shopCategory = Category::create([
        'name' => 'Sofa',
        'slug' => 'sofa',
    ]);

    $product1 = Product::create([
        'category_id' => $shopCategory->id,
        'name' => 'Sofa Da Ý Moderno',
        'slug' => 'sofa-da-y-moderno',
        'sku' => 'SF-MODERNO-01',
        'price' => 28000000,
        'stock' => 5,
        'status' => 'published',
    ]);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $this->user->id,
        'title' => 'Bí Quyết Chọn Sofa Cho Chung Cư Nhỏ',
        'slug' => 'bi-quyet-chon-sofa-cho-chung-cu-nho',
        'excerpt' => 'Kinh nghiệm chọn sofa nhỏ gọn.',
        'body' => '<p>Nội dung ban đầu...</p>',
        'status' => 'draft',
    ]);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->fillForm([
            'title' => 'Bí Quyết Chọn Sofa Cho Chung Cư Nhỏ (Cập Nhật 2026)',
            'status' => 'published',
            'is_featured' => true,
            'products' => [$product1->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();
    expect($post->title)->toBe('Bí Quyết Chọn Sofa Cho Chung Cư Nhỏ (Cập Nhật 2026)');
    expect($post->status)->toBe('published');
    expect($post->is_featured)->toBeTrue();
    expect($post->products)->toHaveCount(1);
    expect($post->products->first()->id)->toBe($product1->id);
});

test('rejects post creation with reserved system slug', function (string $reservedSlug) {
    $category = PostCategory::create([
        'name' => 'Tin Tức',
        'slug' => 'tin-tuc',
    ]);

    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Bài Viết Trùng Route',
            'slug' => $reservedSlug,
            'excerpt' => 'Tóm tắt.',
            'body' => '<p>Nội dung.</p>',
            'post_category_id' => $category->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    $this->assertDatabaseMissing('posts', [
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
    'api',
    'shield',
]);

test('rejects duplicate post slug', function () {
    $category = PostCategory::create([
        'name' => 'Tin Tức',
        'slug' => 'tin-tuc',
    ]);

    Post::create([
        'post_category_id' => $category->id,
        'user_id' => $this->user->id,
        'title' => 'Bài Viết Gốc',
        'slug' => 'bai-viet-doc-nhat',
        'body' => '<p>Nội dung.</p>',
        'status' => 'published',
    ]);

    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Bài Viết Trùng Slug',
            'slug' => 'bai-viet-doc-nhat',
            'body' => '<p>Nội dung mới.</p>',
            'post_category_id' => $category->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});
