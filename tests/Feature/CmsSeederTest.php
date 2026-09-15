<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\TocService;
use Database\Seeders\CmsSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('cms seeder seeds 3 post categories with correct attributes, active status and sort orders', function () {
    $this->seed(CmsSeeder::class);

    expect(PostCategory::count())->toBe(3);

    $expectedCategories = [
        [
            'slug' => 'kien-thuc-noi-that',
            'name' => 'Kiến Thức Nội Thất',
            'sort_order' => 1,
            'is_active' => true,
        ],
        [
            'slug' => 'phong-cach-song',
            'name' => 'Phong Cách Sống',
            'sort_order' => 2,
            'is_active' => true,
        ],
        [
            'slug' => 'huong-dan-bao-quan',
            'name' => 'Hướng Dẫn Bảo Quản',
            'sort_order' => 3,
            'is_active' => true,
        ],
    ];

    foreach ($expectedCategories as $expected) {
        $category = PostCategory::where('slug', $expected['slug'])->first();

        expect($category)->not->toBeNull()
            ->and($category->name)->toBe($expected['name'])
            ->and($category->sort_order)->toBe($expected['sort_order'])
            ->and($category->is_active)->toBe($expected['is_active'])
            ->and($category->description)->not->toBeEmpty()
            ->and($category->seo_title)->not->toBeEmpty()
            ->and($category->seo_description)->not->toBeEmpty();
    }

    $activeOrdered = PostCategory::active()->ordered()->get();
    expect($activeOrdered->pluck('slug')->toArray())->toBe([
        'kien-thuc-noi-that',
        'phong-cach-song',
        'huong-dan-bao-quan',
    ]);
});

test('cms seeder seeds 5 detailed scandinavian articles with author, published status and reading time', function () {
    // Pre-create admin user so author relation can attach
    $admin = User::firstOrCreate(
        ['email' => 'admin@example.com'],
        ['name' => 'Admin Sober', 'password' => bcrypt('password')]
    );

    $this->seed(CmsSeeder::class);

    expect(Post::count())->toBe(5);

    // All posts must be published and dated in past
    $publishedPosts = Post::published()->get();
    expect($publishedPosts->count())->toBe(5);

    // At least 2 featured posts
    $featuredPosts = Post::featured()->get();
    expect($featuredPosts->count())->toBeGreaterThanOrEqual(2);

    $expectedSlugs = [
        'nghe-thuat-bai-tri-anh-sang-scandinavian',
        'bi-quyet-lua-chon-va-bao-quan-ban-ghe-go-soi',
        'xu-huong-thiet-ke-noi-that-toi-gian-2026',
        'cach-phoi-hop-mau-sac-trung-tinh-va-chat-lieu-tho-moc',
        'cam-nang-chon-sofa-bang-vai-bo-cao-cap',
    ];

    foreach ($expectedSlugs as $slug) {
        $post = Post::where('slug', $slug)->first();

        expect($post)->not->toBeNull()
            ->and($post->title)->not->toBeEmpty()
            ->and($post->excerpt)->not->toBeEmpty()
            ->and($post->body)->not->toBeEmpty()
            ->and($post->content)->not->toBeEmpty()
            ->and($post->status)->toBe('published')
            ->and($post->published_at)->not->toBeNull()
            ->and($post->reading_time_minutes)->toBeGreaterThanOrEqual(1)
            ->and($post->post_category_id)->not->toBeNull()
            ->and($post->category)->not->toBeNull()
            ->and($post->featured_image)->not->toBeEmpty()
            ->and($post->banner_image)->not->toBeEmpty()
            ->and($post->og_image)->not->toBeEmpty()
            ->and($post->featured_image_url)->not->toBeEmpty()
            ->and($post->banner_image_url)->not->toBeEmpty()
            ->and($post->og_image_url)->not->toBeEmpty()
            ->and($post->seo_title)->not->toBeEmpty()
            ->and($post->seo_description)->not->toBeEmpty()
            ->and($post->schema_type)->toBeIn(['BlogPosting', 'Article', 'NewsArticle']);

        expect($post->user_id)->toBe($admin->id);
        expect($post->author)->not->toBeNull();
    }
});

test('cms seeder articles contain valid h2 and h3 headings parseable by TocService', function () {
    $this->seed(CmsSeeder::class);

    $tocService = app(TocService::class);
    $posts = Post::all();

    foreach ($posts as $post) {
        $result = $tocService->generate($post->body);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['toc', 'html']);

        $toc = $result['toc'];
        expect($toc)->toBeArray()
            ->and(count($toc))->toBeGreaterThanOrEqual(4);

        // Verify structure of each TOC item
        foreach ($toc as $item) {
            expect($item)->toHaveKeys(['id', 'title', 'level'])
                ->and($item['level'])->toBeIn([2, 3])
                ->and($item['title'])->toBeString()->not->toBeEmpty()
                ->and($item['id'])->toBeString()->not->toBeEmpty();
        }

        // Verify HTML transformation injects heading IDs
        expect($result['html'])->toContain('id="');
    }
});

test('cms seeder articles have valid structured faq schema data', function () {
    $this->seed(CmsSeeder::class);

    $posts = Post::all();

    foreach ($posts as $post) {
        expect($post->faq_schema)->toBeArray()
            ->and(count($post->faq_schema))->toBeGreaterThanOrEqual(2)
            ->and(count($post->faq_schema))->toBeLessThanOrEqual(5);

        foreach ($post->faq_schema as $faq) {
            expect($faq)->toHaveKeys(['question', 'answer'])
                ->and($faq['question'])->toBeString()->not->toBeEmpty()
                ->and($faq['answer'])->toBeString()->not->toBeEmpty();
        }
    }
});

test('cms seeder attaches existing products via contextual commerce post_product pivot table with sort order', function () {
    // Pre-create sample products
    $cat = Category::create(['name' => 'Ghế & Armchair', 'slug' => 'ghe-armchair']);
    $p1 = Product::create([
        'category_id' => $cat->id,
        'name' => 'Đèn Thả Trần Ambit',
        'slug' => 'ambit-pendant-lamp',
        'sku' => 'LMP-001',
        'price' => 4500000,
        'stock' => 20,
        'weight' => 1500,
        'status' => 'published',
    ]);
    $p2 = Product::create([
        'category_id' => $cat->id,
        'name' => 'Ghế Ăn Synnes',
        'slug' => 'synnes-dining-chair',
        'sku' => 'CHR-004',
        'price' => 5800000,
        'stock' => 15,
        'weight' => 4500,
        'status' => 'published',
    ]);
    $p3 = Product::create([
        'category_id' => $cat->id,
        'name' => 'Đồng Hồ Freakish',
        'slug' => 'freakish-clock',
        'sku' => 'ACC-003',
        'price' => 2650000,
        'stock' => 25,
        'weight' => 800,
        'status' => 'published',
    ]);
    $p4 = Product::create([
        'category_id' => $cat->id,
        'name' => 'Bộ Cối Xay Tiêu',
        'slug' => 'bottle-grinders-set',
        'sku' => 'ACC-002',
        'price' => 1250000,
        'stock' => 30,
        'weight' => 600,
        'status' => 'published',
    ]);
    $p5 = Product::create([
        'category_id' => $cat->id,
        'name' => 'Đèn Bàn Xi Măng',
        'slug' => 'cement-wood-lamp',
        'sku' => 'LMP-007',
        'price' => 2150000,
        'stock' => 18,
        'weight' => 2400,
        'status' => 'published',
    ]);

    $this->seed(CmsSeeder::class);

    $lightingPost = Post::where('slug', 'nghe-thuat-bai-tri-anh-sang-scandinavian')->first();
    expect($lightingPost)->not->toBeNull();

    $attachedProducts = $lightingPost->products;
    expect($attachedProducts->count())->toBe(3)
        ->and($attachedProducts->first()->slug)->toBe('ambit-pendant-lamp')
        ->and($attachedProducts->first()->pivot->sort_order)->toBe(1);

    // Verify reverse relationship on Product model
    $synnesChair = Product::where('slug', 'synnes-dining-chair')->first();
    expect($synnesChair->posts)->not->toBeEmpty();
    expect($synnesChair->posts->pluck('slug'))->toContain('nghe-thuat-bai-tri-anh-sang-scandinavian');

    // Verify pivot records exist in database
    expect(DB::table('post_product')->count())->toBeGreaterThanOrEqual(10);
});

test('cms seeder seeds 3 policy pages with policy template, valid body and faq schemas', function () {
    $this->seed(CmsSeeder::class);

    expect(Page::count())->toBe(3);
    expect(Page::published()->count())->toBe(3);

    $expectedPages = [
        'chinh-sach-bao-mat' => 'Chính Sách Bảo Mật',
        'dieu-khoan-dich-vu' => 'Điều Khoản Dịch Vụ',
        'chinh-sach-van-chuyen-doi-tra' => 'Chính Sách Vận Chuyển & Đổi Trả',
    ];

    foreach ($expectedPages as $slug => $title) {
        $page = Page::where('slug', $slug)->first();

        expect($page)->not->toBeNull()
            ->and($page->title)->toBe($title)
            ->and($page->template)->toBe('policy')
            ->and($page->is_published)->toBeTrue()
            ->and($page->published_at)->not->toBeNull()
            ->and($page->body)->not->toBeEmpty()
            ->and($page->content)->not->toBeEmpty()
            ->and($page->excerpt)->not->toBeEmpty()
            ->and($page->seo_title)->not->toBeEmpty()
            ->and($page->seo_description)->not->toBeEmpty()
            ->and($page->faq_schema)->toBeArray()
            ->and(count($page->faq_schema))->toBeGreaterThanOrEqual(2);

        foreach ($page->faq_schema as $faq) {
            expect($faq)->toHaveKeys(['question', 'answer'])
                ->and($faq['question'])->toBeString()->not->toBeEmpty()
                ->and($faq['answer'])->toBeString()->not->toBeEmpty();
        }
    }
});

test('cms seeder is idempotent and maintains exact record counts on consecutive executions', function () {
    // Seed initial products
    $this->seed(DatabaseSeeder::class);

    $postCountInitial = Post::count();
    $catCountInitial = PostCategory::count();
    $pageCountInitial = Page::count();
    $pivotCountInitial = DB::table('post_product')->count();

    expect($postCountInitial)->toBe(5);
    expect($catCountInitial)->toBe(3);
    expect($pageCountInitial)->toBe(3);
    expect($pivotCountInitial)->toBeGreaterThanOrEqual(10);

    // Run CmsSeeder a second time
    $this->seed(CmsSeeder::class);

    expect(Post::count())->toBe(5);
    expect(PostCategory::count())->toBe(3);
    expect(Page::count())->toBe(3);
    expect(DB::table('post_product')->count())->toBe($pivotCountInitial);
});

test('database seeder executes complete application seed including cms articles, categories, and policy pages', function () {
    $this->seed(DatabaseSeeder::class);

    // Verify eCommerce catalog
    expect(Category::count())->toBeGreaterThanOrEqual(6);
    expect(Product::count())->toBeGreaterThanOrEqual(10);

    // Verify CMS Data
    expect(PostCategory::count())->toBe(3);
    expect(Post::count())->toBe(5);
    expect(Page::count())->toBe(3);

    // Verify eCommerce <-> Blog linkages
    $featuredPost = Post::featured()->first();
    expect($featuredPost)->not->toBeNull();
    expect($featuredPost->products)->not->toBeEmpty();

    $sampleProduct = Product::where('slug', 'ambit-pendant-lamp')->first();
    expect($sampleProduct)->not->toBeNull();
    expect($sampleProduct->posts)->not->toBeEmpty();
});
