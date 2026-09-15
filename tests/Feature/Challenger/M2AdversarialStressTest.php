<?php

use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use App\Rules\ReservedRouteRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'admin_challenger@example.com',
    ]);

    $permissions = [
        'view_any_post', 'view_post', 'create_post', 'update_post', 'delete_post', 'delete_any_post',
        'view_any_post::category', 'view_post::category', 'create_post::category', 'update_post::category', 'delete_post::category',
        'view_any_page', 'view_page', 'create_page', 'update_page', 'delete_page',
    ];

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);
});

describe('ReservedRouteRule Adversarial Stress Matrix', function () {
    test('rejects all reserved slugs regardless of casing, slashes, or whitespace', function (string $input) {
        $rule = new ReservedRouteRule;
        $failed = false;
        $failureMessage = null;

        $rule->validate('slug', $input, function ($message) use (&$failed, &$failureMessage) {
            $failed = true;
            $failureMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failureMessage)->toContain('conflicts with a reserved system route');
    })->with([
        'admin',
        '/admin',
        'admin/',
        '/admin/',
        '/AdMiN/',
        '  CHECKOUT  ',
        '//cart//',
        'products',
        'PRODUCTS',
        '/PRODUCTS/',
        'shield',
        '/SHIELD/',
        'livewire',
        'storage',
        'api',
        '/API/',
        'health',
        'telescope',
        'horizon',
        'categories',
        'CATEGORIES',
        'login',
        'LOGIN',
        'register',
        'REGISTER',
        'logout',
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
        'up',
        'settings',
        'sanctum',
        'oauth',
    ]);

    test('permits legitimate custom slugs with reserved substrings or prefixes', function (string $input) {
        $rule = new ReservedRouteRule;
        $failed = false;

        $rule->validate('slug', $input, function () use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    })->with([
        'admin-dashboard',
        'blog-post-1',
        'products-and-services',
        'my-cart-review',
        'checkout-guide',
        'login-help',
        'api-documentation',
        'about-our-craft',
        'shield-defense',
        'ban-an-go-soi',
        'chinh-sach-doi-tra-30-ngay',
        'xu-huong-scandinavian-2026',
    ]);

    test('handles extreme strings and edge case tokens without crashing', function (mixed $input) {
        $rule = new ReservedRouteRule;
        $failed = false;

        $rule->validate('slug', $input, function () use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    })->with([
        '',
        null,
        0,
        '0',
        '   ',
        str_repeat('a', 1000),
        str_repeat('admin-', 200),
        '../admin',
        '../../cart',
        'admin/..',
        'admіn',
        'ban-ghe-go',
        '<script>alert(1)</script>',
        'SELECT * FROM users',
    ]);
});

describe('PostResource Form Handling & Commerce Pivot', function () {
    test('creates post and synchronizes contextual commerce products to post_product pivot table', function () {
        $category = PostCategory::create([
            'name' => 'Kien Truc',
            'slug' => 'kien-truc',
        ]);

        $shopCategory = Category::create([
            'name' => 'Sofa',
            'slug' => 'sofa',
        ]);

        $product1 = Product::create([
            'category_id' => $shopCategory->id,
            'name' => 'Sofa Da That',
            'slug' => 'sofa-da-that',
            'sku' => 'SF-01',
            'price' => 15000000,
            'stock' => 10,
            'status' => 'published',
        ]);

        $product2 = Product::create([
            'category_id' => $shopCategory->id,
            'name' => 'Ban Tra Go Oc Cho',
            'slug' => 'ban-tra-go-oc-cho',
            'sku' => 'BT-02',
            'price' => 8000000,
            'stock' => 5,
            'status' => 'published',
        ]);

        $faqPayload = [
            ['question' => 'Sofa co bao hanh khong?', 'answer' => 'Bao hanh da 3 nam, khung 10 nam.'],
            ['question' => 'Co giao hang toan quoc?', 'answer' => 'Mien phi van chuyen noi thanh.'],
        ];

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Bo Suu Tap Phong Khach Bac Au 2026',
                'slug' => 'bo-suu-tap-phong-khach-bac-au-2026',
                'excerpt' => 'Tom tat bai viet phong khach.',
                'body' => '<p>Noi dung chi tiet ve cac san pham noi that cao cap.</p>',
                'post_category_id' => $category->id,
                'user_id' => $this->user->id,
                'status' => 'published',
                'is_featured' => true,
                'products' => [$product1->id, $product2->id],
                'schema_type' => 'Article',
                'faq_schema' => $faqPayload,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'bo-suu-tap-phong-khach-bac-au-2026')->first();
        expect($post)->not->toBeNull();
        expect($post->products)->toHaveCount(2);

        $pivotRows = DB::table('post_product')->where('post_id', $post->id)->get();
        expect($pivotRows)->toHaveCount(2);
        expect($pivotRows->pluck('product_id')->all())->toContain($product1->id, $product2->id);

        expect($post->faq_schema)->toBeArray();
        expect($post->faq_schema)->toHaveCount(2);
        expect($post->faq_schema[0]['question'])->toBe('Sofa co bao hanh khong?');
        expect($post->faq_schema[1]['answer'])->toBe('Mien phi van chuyen noi thanh.');

        expect($post->reading_time_minutes)->toBeGreaterThanOrEqual(1);
    });

    test('updates post and synchronizes mutated product relationships', function () {
        $category = PostCategory::create([
            'name' => 'Cam Nang',
            'slug' => 'cam-nang',
        ]);

        $shopCategory = Category::create([
            'name' => 'Ghe',
            'slug' => 'ghe',
        ]);

        $productA = Product::create(['category_id' => $shopCategory->id, 'name' => 'Ghe A', 'slug' => 'ghe-a', 'sku' => 'GH-A', 'price' => 1000, 'stock' => 1, 'status' => 'published']);
        $productB = Product::create(['category_id' => $shopCategory->id, 'name' => 'Ghe B', 'slug' => 'ghe-b', 'sku' => 'GH-B', 'price' => 2000, 'stock' => 1, 'status' => 'published']);
        $productC = Product::create(['category_id' => $shopCategory->id, 'name' => 'Ghe C', 'slug' => 'ghe-c', 'sku' => 'GH-C', 'price' => 3000, 'stock' => 1, 'status' => 'published']);

        $post = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'title' => 'Top Ghe An',
            'slug' => 'top-ghe-an',
            'body' => '<p>Noi dung</p>',
            'status' => 'published',
        ]);

        $post->products()->attach([$productA->id, $productB->id]);
        expect($post->products()->count())->toBe(2);

        // Mutate products: remove B, add C
        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'products' => [$productA->id, $productC->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $post->refresh();
        $attachedIds = $post->products()->pluck('products.id')->all();
        expect($attachedIds)->toHaveCount(2);
        expect($attachedIds)->toContain($productA->id, $productC->id);
        expect($attachedIds)->not->toContain($productB->id);

        // Detach all products
        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'products' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $post->refresh();
        expect($post->products()->count())->toBe(0);
        expect(DB::table('post_product')->where('post_id', $post->id)->count())->toBe(0);
    });

    test('filters published vs draft vs scheduled vs archived posts correctly via model scopes', function () {
        $category = PostCategory::create(['name' => 'News', 'slug' => 'news']);

        $publishedPost = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'title' => 'Published Post',
            'slug' => 'published-post',
            'body' => '<p>Pub</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'is_featured' => true,
        ]);

        $draftPost = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'body' => '<p>Draft</p>',
            'status' => 'draft',
            'is_featured' => true,
        ]);

        $scheduledPost = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'title' => 'Scheduled Post',
            'slug' => 'scheduled-post',
            'body' => '<p>Scheduled</p>',
            'status' => 'published',
            'published_at' => now()->addDays(3),
            'is_featured' => true,
        ]);

        $archivedPost = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'title' => 'Archived Post',
            'slug' => 'archived-post',
            'body' => '<p>Archived</p>',
            'status' => 'archived',
            'is_featured' => true,
        ]);

        $publishedIds = Post::published()->pluck('id')->all();
        expect($publishedIds)->toContain($publishedPost->id);
        expect($publishedIds)->not->toContain($draftPost->id);
        expect($publishedIds)->not->toContain($scheduledPost->id);
        expect($publishedIds)->not->toContain($archivedPost->id);

        $featuredIds = Post::featured()->pluck('id')->all();
        expect($featuredIds)->toContain($publishedPost->id);
        expect($featuredIds)->not->toContain($draftPost->id);
        expect($featuredIds)->not->toContain($scheduledPost->id);
    });

    test('enforces slug uniqueness and allows editing record with same slug', function () {
        $category = PostCategory::create(['name' => 'Tin Tuc', 'slug' => 'tin-tuc']);

        $existingPost = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'title' => 'Bai Viet Dau Tien',
            'slug' => 'bai-viet-dau-tien',
            'body' => '<p>First</p>',
            'status' => 'published',
        ]);

        // Attempt to create duplicate slug
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Bai Viet Trung',
                'slug' => 'bai-viet-dau-tien',
                'body' => '<p>Duplicate</p>',
                'post_category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        // Allow editing own slug
        Livewire::test(EditPost::class, ['record' => $existingPost->getKey()])
            ->fillForm([
                'title' => 'Bai Viet Dau Tien (Da Doi Tieu De)',
                'slug' => 'bai-viet-dau-tien',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Reject editing to another existing slug
        $secondPost = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $this->user->id,
            'title' => 'Bai Viet Thu Hai',
            'slug' => 'bai-viet-thu-hai',
            'body' => '<p>Second</p>',
            'status' => 'published',
        ]);

        Livewire::test(EditPost::class, ['record' => $secondPost->getKey()])
            ->fillForm([
                'slug' => 'bai-viet-dau-tien',
            ])
            ->call('save')
            ->assertHasFormErrors(['slug']);
    });
});
