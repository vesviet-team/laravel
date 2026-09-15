<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| PAGE MODEL ADVERSARIAL TESTS
|--------------------------------------------------------------------------
*/

describe('Page Model Adversarial Tests', function () {
    test('scopePublished truth table: is_published vs published_at (null, past, future)', function () {
        $now = Carbon::parse('2026-08-19 12:00:00');
        Carbon::setTestNow($now);

        // Case 1: is_published=true, published_at=null -> PUBLISHED
        $pubNull = Page::create([
            'title' => 'Page True Null',
            'slug' => 'page-true-null',
            'body' => 'Content',
            'is_published' => true,
            'published_at' => null,
        ]);

        // Case 2: is_published=true, published_at=past -> PUBLISHED
        $pubPast = Page::create([
            'title' => 'Page True Past',
            'slug' => 'page-true-past',
            'body' => 'Content',
            'is_published' => true,
            'published_at' => $now->copy()->subHours(2),
        ]);

        // Case 3: is_published=true, published_at=now -> PUBLISHED
        $pubNow = Page::create([
            'title' => 'Page True Now',
            'slug' => 'page-true-now',
            'body' => 'Content',
            'is_published' => true,
            'published_at' => $now,
        ]);

        // Case 4: is_published=true, published_at=future -> NOT PUBLISHED
        $pubFuture = Page::create([
            'title' => 'Page True Future',
            'slug' => 'page-true-future',
            'body' => 'Content',
            'is_published' => true,
            'published_at' => $now->copy()->addMinutes(5),
        ]);

        // Case 5: is_published=false, published_at=null -> NOT PUBLISHED
        $unpubNull = Page::create([
            'title' => 'Page False Null',
            'slug' => 'page-false-null',
            'body' => 'Content',
            'is_published' => false,
            'published_at' => null,
        ]);

        // Case 6: is_published=false, published_at=past -> NOT PUBLISHED
        $unpubPast = Page::create([
            'title' => 'Page False Past',
            'slug' => 'page-false-past',
            'body' => 'Content',
            'is_published' => false,
            'published_at' => $now->copy()->subDays(5),
        ]);

        // Case 7: is_published=false, published_at=future -> NOT PUBLISHED
        $unpubFuture = Page::create([
            'title' => 'Page False Future',
            'slug' => 'page-false-future',
            'body' => 'Content',
            'is_published' => false,
            'published_at' => $now->copy()->addDays(5),
        ]);

        $publishedPages = Page::published()->get();

        expect($publishedPages)->toHaveCount(3);
        $ids = $publishedPages->pluck('id')->all();

        expect($ids)->toContain($pubNull->id);
        expect($ids)->toContain($pubPast->id);
        expect($ids)->toContain($pubNow->id);

        expect($ids)->not->toContain($pubFuture->id);
        expect($ids)->not->toContain($unpubNull->id);
        expect($ids)->not->toContain($unpubPast->id);
        expect($ids)->not->toContain($unpubFuture->id);

        Carbon::setTestNow();
    });

    test('content accessor and mutator bidirectional alias with body', function () {
        // Test direct mutation via content property
        $page = new Page;
        $page->title = 'Alias Test';
        $page->slug = 'alias-test';
        $page->content = '<h2>Custom Title</h2><p>Paragraph content</p>';

        expect($page->body)->toBe('<h2>Custom Title</h2><p>Paragraph content</p>');
        expect($page->content)->toBe('<h2>Custom Title</h2><p>Paragraph content</p>');

        // Test mutation via body property
        $page->body = '<p>Replaced via body</p>';
        expect($page->content)->toBe('<p>Replaced via body</p>');
        expect($page->body)->toBe('<p>Replaced via body</p>');

        // Test mass assignment via create with content
        $persisted = Page::create([
            'title' => 'Mass Assigned Content',
            'slug' => 'mass-assigned-content',
            'content' => '<p>Created with content key</p>',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('pages', [
            'id' => $persisted->id,
            'body' => '<p>Created with content key</p>',
        ]);

        // Test update via content key
        $persisted->update([
            'content' => '<p>Updated via content key</p>',
        ]);

        expect($persisted->fresh()->body)->toBe('<p>Updated via content key</p>');
        expect($persisted->fresh()->content)->toBe('<p>Updated via content key</p>');

        // Test nullifying content
        $persisted->content = null;
        expect($persisted->body)->toBeNull();
        expect($persisted->content)->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| POSTCATEGORY MODEL ADVERSARIAL TESTS
|--------------------------------------------------------------------------
*/

describe('PostCategory Model Adversarial Tests', function () {
    test('active scope strictly isolates active vs inactive categories', function () {
        $activeA = PostCategory::create(['name' => 'Cat 1', 'slug' => 'cat-1', 'is_active' => true]);
        $activeB = PostCategory::create(['name' => 'Cat 2', 'slug' => 'cat-2', 'is_active' => 1]);
        $inactiveA = PostCategory::create(['name' => 'Cat 3', 'slug' => 'cat-3', 'is_active' => false]);
        $inactiveB = PostCategory::create(['name' => 'Cat 4', 'slug' => 'cat-4', 'is_active' => 0]);

        $activeResults = PostCategory::active()->get();

        expect($activeResults)->toHaveCount(2);
        expect($activeResults->pluck('id')->all())->toEqualCanonicalizing([$activeA->id, $activeB->id]);
        expect($activeResults->pluck('id')->all())->not->toContain($inactiveA->id, $inactiveB->id);
    });

    test('ordered scope handles negative, zero, duplicate, and high sort_order values with alphabetical tie breaker', function () {
        $cHigh = PostCategory::create(['name' => 'Omega High', 'slug' => 'high', 'sort_order' => 99999]);
        $cNegLarge = PostCategory::create(['name' => 'Neg Large', 'slug' => 'neg-large', 'sort_order' => -100]);
        $cNegSmall = PostCategory::create(['name' => 'Neg Small', 'slug' => 'neg-small', 'sort_order' => -1]);
        $cZeroB = PostCategory::create(['name' => 'Zero Beta', 'slug' => 'zero-beta', 'sort_order' => 0]);
        $cZeroA = PostCategory::create(['name' => 'Zero Alpha', 'slug' => 'zero-alpha', 'sort_order' => 0]);
        $cMed = PostCategory::create(['name' => 'Medium', 'slug' => 'medium', 'sort_order' => 50]);

        $ordered = PostCategory::ordered()->get();

        $expectedSlugs = [
            'neg-large',  // sort_order = -100
            'neg-small',  // sort_order = -1
            'zero-alpha', // sort_order = 0, name = Zero Alpha
            'zero-beta',  // sort_order = 0, name = Zero Beta
            'medium',     // sort_order = 50
            'high',       // sort_order = 99999
        ];

        expect($ordered->pluck('slug')->all())->toBe($expectedSlugs);
    });

    test('active and ordered scopes combined ignore inactive regardless of negative sort_order', function () {
        $inactiveTop = PostCategory::create([
            'name' => 'Inactive Priority',
            'slug' => 'inactive-top',
            'is_active' => false,
            'sort_order' => -9999,
        ]);

        $activeNormal = PostCategory::create([
            'name' => 'Active Normal',
            'slug' => 'active-normal',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $results = PostCategory::active()->ordered()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($activeNormal->id);
    });
});

/*
|--------------------------------------------------------------------------
| CONTEXTUAL COMMERCE POST-PRODUCT RELATION & PIVOT TESTS
|--------------------------------------------------------------------------
*/

describe('Post-Product Contextual Commerce Pivot Tests', function () {
    test('bidirectional sync and pivot attributes sort_order and timestamps', function () {
        $cat = PostCategory::create(['name' => 'Design', 'slug' => 'design']);
        $user = User::factory()->create();
        $ecomCat = Category::create(['name' => 'Chairs', 'slug' => 'chairs']);

        $productA = Product::create([
            'category_id' => $ecomCat->id,
            'name' => 'Product Alpha',
            'slug' => 'product-alpha',
            'sku' => 'PA-01',
            'price' => 1000000,
            'stock' => 10,
            'status' => 'published',
        ]);

        $productB = Product::create([
            'category_id' => $ecomCat->id,
            'name' => 'Product Beta',
            'slug' => 'product-beta',
            'sku' => 'PB-02',
            'price' => 2000000,
            'stock' => 5,
            'status' => 'published',
        ]);

        $productC = Product::create([
            'category_id' => $ecomCat->id,
            'name' => 'Product Gamma',
            'slug' => 'product-gamma',
            'sku' => 'PC-03',
            'price' => 3000000,
            'stock' => 2,
            'status' => 'published',
        ]);

        $post = Post::create([
            'post_category_id' => $cat->id,
            'user_id' => $user->id,
            'title' => 'Contextual Commerce Article',
            'slug' => 'contextual-commerce-article',
            'body' => '<p>Discover our top picks.</p>',
            'status' => 'published',
        ]);

        // Sync 3 products with non-sequential sort orders
        $post->products()->sync([
            $productA->id => ['sort_order' => 30],
            $productB->id => ['sort_order' => 10],
            $productC->id => ['sort_order' => 20],
        ]);

        $loadedProducts = $post->fresh()->products;

        expect($loadedProducts)->toHaveCount(3);
        // Post::products() orders by sort_order asc: B (10) -> C (20) -> A (30)
        expect($loadedProducts[0]->id)->toBe($productB->id);
        expect($loadedProducts[1]->id)->toBe($productC->id);
        expect($loadedProducts[2]->id)->toBe($productA->id);

        expect($loadedProducts[0]->pivot->sort_order)->toBe(10);
        expect($loadedProducts[1]->pivot->sort_order)->toBe(20);
        expect($loadedProducts[2]->pivot->sort_order)->toBe(30);

        // Verify timestamps on pivot
        expect($loadedProducts[0]->pivot->created_at)->not->toBeNull();
        expect($loadedProducts[0]->pivot->updated_at)->not->toBeNull();

        // Reverse verification from Product side
        $pBPosts = $productB->fresh()->posts;
        expect($pBPosts)->toHaveCount(1);
        expect($pBPosts->first()->id)->toBe($post->id);
        expect($pBPosts->first()->pivot->sort_order)->toBe(10);

        // Mutate pivot from Product side
        $productA->posts()->updateExistingPivot($post->id, ['sort_order' => 5]);

        // Now sort order: A (5) -> B (10) -> C (20)
        $reordered = $post->fresh()->products;
        expect($reordered[0]->id)->toBe($productA->id);
        expect($reordered[1]->id)->toBe($productB->id);
        expect($reordered[2]->id)->toBe($productC->id);
    });

    test('foreign key cascades delete pivot records on post or product deletion', function () {
        $cat = PostCategory::create(['name' => 'Decor', 'slug' => 'decor']);
        $user = User::factory()->create();
        $ecomCat = Category::create(['name' => 'Tables', 'slug' => 'tables']);

        $product = Product::create([
            'category_id' => $ecomCat->id,
            'name' => 'Dining Table',
            'slug' => 'dining-table',
            'sku' => 'DT-01',
            'price' => 5000000,
            'stock' => 3,
            'status' => 'published',
        ]);

        $post = Post::create([
            'post_category_id' => $cat->id,
            'user_id' => $user->id,
            'title' => 'Dining Table Styling Guide',
            'slug' => 'dining-table-styling',
            'body' => '<p>Styling tips.</p>',
            'status' => 'published',
        ]);

        $post->products()->attach($product->id, ['sort_order' => 1]);

        $this->assertDatabaseHas('post_product', [
            'post_id' => $post->id,
            'product_id' => $product->id,
        ]);

        // Delete post -> pivot row should be cascaded
        $post->forceDelete();

        $this->assertDatabaseMissing('post_product', [
            'post_id' => $post->id,
            'product_id' => $product->id,
        ]);
    });

    test('unique constraint prevents duplicate post_product associations', function () {
        $cat = PostCategory::create(['name' => 'Unique Test', 'slug' => 'unique-test']);
        $user = User::factory()->create();
        $ecomCat = Category::create(['name' => 'Desks', 'slug' => 'desks']);

        $product = Product::create([
            'category_id' => $ecomCat->id,
            'name' => 'Office Desk',
            'slug' => 'office-desk',
            'sku' => 'OD-01',
            'price' => 4000000,
            'stock' => 2,
            'status' => 'published',
        ]);

        $post = Post::create([
            'post_category_id' => $cat->id,
            'user_id' => $user->id,
            'title' => 'Desk Setup Guide',
            'slug' => 'desk-setup-guide',
            'body' => '<p>Ergonomics.</p>',
            'status' => 'published',
        ]);

        DB::table('post_product')->insert([
            'post_id' => $post->id,
            'product_id' => $product->id,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting duplicate insert directly into DB must throw QueryException
        expect(fn () => DB::table('post_product')->insert([
            'post_id' => $post->id,
            'product_id' => $product->id,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});
