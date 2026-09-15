<?php

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('adversarial stress: 5 consecutive executions of CmsSeeder maintain absolute idempotency without row expansion or ID churn', function () {
    $this->seed(DatabaseSeeder::class);

    $initialPosts = Post::orderBy('id')->get();
    $initialCategories = PostCategory::orderBy('id')->get();
    $initialPages = Page::orderBy('id')->get();
    $initialPivotRows = DB::table('post_product')->orderBy('id')->get();

    expect($initialCategories->count())->toBe(3)
        ->and($initialPosts->count())->toBe(5)
        ->and($initialPages->count())->toBe(3)
        ->and($initialPivotRows->count())->toBeGreaterThanOrEqual(10);

    $initialPostIds = $initialPosts->pluck('id')->toArray();
    $initialCatIds = $initialCategories->pluck('id')->toArray();
    $initialPageIds = $initialPages->pluck('id')->toArray();
    $initialPivotIds = $initialPivotRows->pluck('id')->toArray();

    // Stress: Run CmsSeeder 5 consecutive times
    for ($i = 1; $i <= 5; $i++) {
        $this->seed(CmsSeeder::class);
    }

    // Verify counts are strictly identical
    expect(PostCategory::count())->toBe(3)
        ->and(Post::count())->toBe(5)
        ->and(Page::count())->toBe(3)
        ->and(DB::table('post_product')->count())->toBe(count($initialPivotIds));

    // Verify IDs did not churn (no delete & recreate)
    expect(PostCategory::orderBy('id')->pluck('id')->toArray())->toBe($initialCatIds)
        ->and(Post::orderBy('id')->pluck('id')->toArray())->toBe($initialPostIds)
        ->and(Page::orderBy('id')->pluck('id')->toArray())->toBe($initialPageIds);

    // Verify no duplicates in post_product pivot
    $duplicatePivots = DB::table('post_product')
        ->select('post_id', 'product_id', DB::raw('count(*) as cnt'))
        ->groupBy('post_id', 'product_id')
        ->having('cnt', '>', 1)
        ->get();

    expect($duplicatePivots)->toBeEmpty();
});

test('adversarial stress: pivot sort order is strictly preserved and ordered ascending across repeated seedings', function () {
    $this->seed(DatabaseSeeder::class);

    // Seed multiple times to ensure sync does not scramble sort_order
    $this->seed(CmsSeeder::class);
    $this->seed(CmsSeeder::class);

    $posts = Post::with('products')->get();

    foreach ($posts as $post) {
        $products = $post->products;
        expect($products->count())->toBeGreaterThanOrEqual(2);

        $sortOrders = [];
        foreach ($products as $prod) {
            $sortOrders[] = $prod->pivot->sort_order;
        }

        // Must be sequential 1, 2, 3...
        $expectedOrders = range(1, count($products));
        expect($sortOrders)->toBe($expectedOrders);

        // Verify direct DB query matches model relationship order
        $dbOrders = DB::table('post_product')
            ->where('post_id', $post->id)
            ->orderBy('sort_order', 'asc')
            ->pluck('sort_order')
            ->toArray();

        expect($dbOrders)->toBe($expectedOrders);
    }
});

test('adversarial stress: eager loading post with category, products, and author executes in exactly 4 queries with zero N+1', function () {
    $this->seed(DatabaseSeeder::class);

    // Ensure we have 5 posts and multiple products attached
    expect(Post::count())->toBe(5);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $posts = Post::with(['category', 'products', 'author'])->get();

    // Iterate through all posts and accessed relations
    $renderedOutput = [];
    foreach ($posts as $post) {
        $categoryName = $post->category?->name;
        $authorName = $post->author?->name;
        $productCount = $post->products->count();
        $firstProductTitle = $post->products->first()?->name;
        $firstProductSort = $post->products->first()?->pivot?->sort_order;

        $renderedOutput[] = [
            'post' => $post->title,
            'category' => $categoryName,
            'author' => $authorName,
            'products' => $productCount,
            'first_prod' => $firstProductTitle,
            'sort_order' => $firstProductSort,
        ];
    }

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Query 1: select * from posts
    // Query 2: select * from post_categories where id in (...)
    // Query 3: select products.*, post_product.* from products inner join post_product where post_product.post_id in (...) order by post_product.sort_order asc
    // Query 4: select * from users where id in (...)
    expect(count($queries))->toBe(4);
    expect(count($renderedOutput))->toBe(5);

    foreach ($renderedOutput as $entry) {
        expect($entry['category'])->not->toBeNull()
            ->and($entry['author'])->not->toBeNull()
            ->and($entry['products'])->toBeGreaterThanOrEqual(1)
            ->and($entry['first_prod'])->not->toBeNull()
            ->and($entry['sort_order'])->toBe(1);
    }
});

test('adversarial stress: all foreign keys are valid and no orphan records exist across CMS tables', function () {
    $this->seed(DatabaseSeeder::class);

    // Verify every post has an existing post_category
    $invalidPostCategories = DB::table('posts')
        ->leftJoin('post_categories', 'posts.post_category_id', '=', 'post_categories.id')
        ->whereNull('post_categories.id')
        ->whereNotNull('posts.post_category_id')
        ->select('posts.id', 'posts.title')
        ->get();
    expect($invalidPostCategories)->toBeEmpty();

    // Verify every post has an existing user
    $invalidPostUsers = DB::table('posts')
        ->leftJoin('users', 'posts.user_id', '=', 'users.id')
        ->whereNull('users.id')
        ->whereNotNull('posts.user_id')
        ->select('posts.id', 'posts.title')
        ->get();
    expect($invalidPostUsers)->toBeEmpty();

    // Verify every post_product record points to a valid post
    $orphanedPostPivots = DB::table('post_product')
        ->leftJoin('posts', 'post_product.post_id', '=', 'posts.id')
        ->whereNull('posts.id')
        ->get();
    expect($orphanedPostPivots)->toBeEmpty();

    // Verify every post_product record points to a valid product
    $orphanedProductPivots = DB::table('post_product')
        ->leftJoin('products', 'post_product.product_id', '=', 'products.id')
        ->whereNull('products.id')
        ->get();
    expect($orphanedProductPivots)->toBeEmpty();
});

test('adversarial stress: CmsSeeder runs gracefully and without failure when product catalog is completely empty', function () {
    // Run seeder on clean database without products
    $this->seed(CmsSeeder::class);

    expect(PostCategory::count())->toBe(3)
        ->and(Post::count())->toBe(5)
        ->and(Page::count())->toBe(3)
        ->and(DB::table('post_product')->count())->toBe(0);

    // Verify articles are still fully intact
    $posts = Post::all();
    foreach ($posts as $post) {
        expect($post->body)->not->toBeEmpty()
            ->and($post->products)->toBeEmpty();
    }
});

test('adversarial stress: CmsSeeder handles null admin user gracefully without throwing exception', function () {
    // Ensure no users exist in database
    DB::table('users')->delete();

    // Run CmsSeeder
    $this->seed(CmsSeeder::class);

    expect(Post::count())->toBe(5);

    // All posts should have null user_id and succeed
    $posts = Post::all();
    foreach ($posts as $post) {
        expect($post->user_id)->toBeNull()
            ->and($post->author)->toBeNull();
    }
});
