<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('can create and persist a post with complete attributes', function () {
    $category = PostCategory::create(['name' => 'Nội Thất', 'slug' => 'noi-that']);
    $user = User::factory()->create();

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Nghệ Thuật Bài Trí Ánh Sáng Scandinavian',
        'slug' => 'nghe-thuat-bai-tri-anh-sang-scandinavian',
        'excerpt' => 'Tóm tắt bài viết về ánh sáng nội thất.',
        'body' => '<p>Nội dung chi tiết bài viết với đèn chiếu sáng và bàn ăn gỗ sồi.</p>',
        'featured_image' => 'posts/featured-1.jpg',
        'banner_image' => 'posts/banner-1.jpg',
        'status' => 'published',
        'is_featured' => true,
        'meta_title' => 'Nghệ Thuật Bài Trí Ánh Sáng',
        'meta_description' => 'Hướng dẫn bài trí ánh sáng ấm cúng chuẩn Bắc Âu.',
        'meta_keywords' => 'scandinavian, den-chieu-sang, noi-that',
        'canonical_url' => 'https://soberfurniture.vn/blog/nghe-thuat-bai-tri-anh-sang-scandinavian',
        'schema_type' => 'BlogPosting',
        'faq_schema' => [
            [
                'question' => 'Nên chọn ánh sáng nào cho phòng khách?',
                'answer' => 'Nên sử dụng ánh sáng vàng ấm 2700K - 3000K.',
            ],
        ],
    ]);

    expect($post->exists)->toBeTrue();
    expect($post->id)->toBeGreaterThan(0);
    expect($post->title)->toBe('Nghệ Thuật Bài Trí Ánh Sáng Scandinavian');
    expect($post->slug)->toBe('nghe-thuat-bai-tri-anh-sang-scandinavian');
    expect($post->is_featured)->toBeTrue();
    expect($post->status)->toBe('published');
    expect($post->published_at)->not->toBeNull();
    expect($post->faq_schema)->toBeArray();
    expect($post->faq_schema[0]['question'])->toBe('Nên chọn ánh sáng nào cho phòng khách?');

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'slug' => 'nghe-thuat-bai-tri-anh-sang-scandinavian',
    ]);
});

test('calculateReadingTime correctly estimates reading duration', function () {
    // Empty content returns minimum 1
    expect(Post::calculateReadingTime(''))->toBe(1);
    expect(Post::calculateReadingTime(null))->toBe(1);

    // Short text under 200 words returns 1 minute
    $shortText = 'Đây là đoạn văn ngắn dưới hai trăm từ.';
    expect(Post::calculateReadingTime($shortText))->toBe(1);

    // 450 English words -> 3 minutes
    $words450 = implode(' ', array_fill(0, 450, 'furniture'));
    expect(Post::calculateReadingTime($words450))->toBe(3);

    // 400 Vietnamese words -> 2 minutes
    $vnWords400 = implode(' ', array_fill(0, 400, 'bàn'));
    expect(Post::calculateReadingTime($vnWords400))->toBe(2);

    // HTML tags are stripped during calculation
    $htmlContent = '<div>'.implode('</div><div>', array_fill(0, 450, '<p>ghế gỗ sồi cao cấp</p>')).'</div>';
    // 450 * 5 words = 2250 words -> 12 minutes
    expect(Post::calculateReadingTime($htmlContent))->toBe(12);

    // Edge case: Self-closing and inline tags without spaces
    $inlineTags = '<p>Bàn<strong>ăn</strong><em>gỗ</em><br>sồi<hr>Bắc<span>Âu</span></p>';
    // 6 words: "Bàn", "ăn", "gỗ", "sồi", "Bắc", "Âu" -> 1 minute
    expect(Post::calculateReadingTime($inlineTags))->toBe(1);

    // Edge case: Non-breaking spaces and HTML entities
    $entityContent = '<p>Nội&nbsp;thất&amp;kiến&nbsp;trúc</p>';
    // 3 words: "Nội", "thất&kiến", "trúc" -> 1 minute
    expect(Post::calculateReadingTime($entityContent))->toBe(1);

    // Edge case: Script & style tags and comments are ignored
    $scriptContent = '<p>Xin chào</p><script>console.log("many words inside script tag");</script><style>body { color: red; }</style><!-- long comment draft notes --><p>Việt Nam</p>';
    // 4 words: "Xin", "chào", "Việt", "Nam" -> 1 minute
    expect(Post::calculateReadingTime($scriptContent))->toBe(1);

    // Edge case: Multiline tags with attributes
    $multilineContent = "<div class=\"article-content\"\n data-id=\"123\"\n style=\"display:block;\"><p>Nội thất Bắc Âu</p></div>";
    // 4 words: "Nội", "thất", "Bắc", "Âu" -> 1 minute
    expect(Post::calculateReadingTime($multilineContent))->toBe(1);
});

test('automatically calculates reading_time_minutes on saving', function () {
    $category = PostCategory::create(['name' => 'General', 'slug' => 'general']);
    $user = User::factory()->create();

    // 450 words in body
    $body = '<p>'.implode(' ', array_fill(0, 450, 'nội thất Bắc Âu tối giản cao cấp')).'</p>';

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Reading Time Test Post',
        'slug' => 'reading-time-test-post',
        'body' => $body,
        'status' => 'draft',
    ]);

    expect($post->reading_time_minutes)->toBeGreaterThanOrEqual(3);
});

test('automatically sets published_at to now on publish if null', function () {
    $category = PostCategory::create(['name' => 'General', 'slug' => 'general-pub']);
    $user = User::factory()->create();

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Auto Published Post',
        'slug' => 'auto-published-post',
        'body' => 'Content',
        'status' => 'published',
        'published_at' => null,
    ]);

    expect($post->published_at)->not->toBeNull();
    expect($post->published_at->isToday())->toBeTrue();
});

test('does not overwrite explicit published_at timestamp', function () {
    $category = PostCategory::create(['name' => 'General', 'slug' => 'general-pub-2']);
    $user = User::factory()->create();
    $pastDate = Carbon::now()->subDays(10);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Explicit Published Post',
        'slug' => 'explicit-published-post',
        'body' => 'Content',
        'status' => 'published',
        'published_at' => $pastDate,
    ]);

    expect($post->published_at->toDateString())->toBe($pastDate->toDateString());
});

test('leaves published_at as null for draft posts', function () {
    $category = PostCategory::create(['name' => 'General', 'slug' => 'general-draft']);
    $user = User::factory()->create();

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Draft Post',
        'slug' => 'draft-post-no-pub',
        'body' => 'Content',
        'status' => 'draft',
        'published_at' => null,
    ]);

    expect($post->published_at)->toBeNull();
});

test('scopePublished filters only published and past-dated posts', function () {
    $category = PostCategory::create(['name' => 'News', 'slug' => 'news']);
    $user = User::factory()->create();

    $published1 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Published 1',
        'slug' => 'pub-1',
        'body' => 'Body 1',
        'status' => 'published',
        'published_at' => Carbon::now()->subHour(),
    ]);

    $published2 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Published 2',
        'slug' => 'pub-2',
        'body' => 'Body 2',
        'status' => 'published',
        'published_at' => Carbon::now()->subDays(5),
    ]);

    $futurePost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Scheduled Future',
        'slug' => 'future',
        'body' => 'Body future',
        'status' => 'published',
        'published_at' => Carbon::now()->addDays(3),
    ]);

    $draftPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Draft Post',
        'slug' => 'draft',
        'body' => 'Body draft',
        'status' => 'draft',
    ]);

    $results = Post::published()->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('id'))->toContain($published1->id, $published2->id);
    expect($results->pluck('id'))->not->toContain($futurePost->id, $draftPost->id);
});

test('scopeFeatured filters only published and featured posts', function () {
    $category = PostCategory::create(['name' => 'News', 'slug' => 'news-featured']);
    $user = User::factory()->create();

    $featuredPub = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Featured Published',
        'slug' => 'feat-pub',
        'body' => 'Body',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
        'is_featured' => true,
    ]);

    $normalPub = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Normal Published',
        'slug' => 'norm-pub',
        'body' => 'Body',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
        'is_featured' => false,
    ]);

    $featuredDraft = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Featured Draft',
        'slug' => 'feat-draft',
        'body' => 'Body',
        'status' => 'draft',
        'is_featured' => true,
    ]);

    $results = Post::featured()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($featuredPub->id);
});

test('scopeByCategory filters by id, slug string, and model instance', function () {
    $catA = PostCategory::create(['name' => 'Category A', 'slug' => 'cat-a']);
    $catB = PostCategory::create(['name' => 'Category B', 'slug' => 'cat-b']);
    $user = User::factory()->create();

    $postA = Post::create([
        'post_category_id' => $catA->id,
        'user_id' => $user->id,
        'title' => 'Post in A',
        'slug' => 'post-a',
        'body' => 'Body',
        'status' => 'published',
    ]);

    $postB = Post::create([
        'post_category_id' => $catB->id,
        'user_id' => $user->id,
        'title' => 'Post in B',
        'slug' => 'post-b',
        'body' => 'Body',
        'status' => 'published',
    ]);

    // Test with integer ID
    $byId = Post::byCategory($catA->id)->get();
    expect($byId)->toHaveCount(1);
    expect($byId->first()->id)->toBe($postA->id);

    // Test with slug string
    $bySlug = Post::byCategory('cat-b')->get();
    expect($bySlug)->toHaveCount(1);
    expect($bySlug->first()->id)->toBe($postB->id);

    // Test with model instance
    $byModel = Post::byCategory($catA)->get();
    expect($byModel)->toHaveCount(1);
    expect($byModel->first()->id)->toBe($postA->id);
});

test('content and body accessors and mutators work seamlessly', function () {
    $post = new Post;
    $post->content = '<p>Custom Content</p>';

    expect($post->body)->toBe('<p>Custom Content</p>');
    expect($post->content)->toBe('<p>Custom Content</p>');

    $post->body = '<p>Updated Body</p>';
    expect($post->content)->toBe('<p>Updated Body</p>');
});

test('relationships category, postCategory, author, user work properly', function () {
    $category = PostCategory::create(['name' => 'Taxonomy', 'slug' => 'taxonomy']);
    $user = User::factory()->create();

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Relation Post',
        'slug' => 'relation-post',
        'body' => 'Body',
        'status' => 'published',
    ]);

    expect($post->category)->not->toBeNull();
    expect($post->category->id)->toBe($category->id);

    expect($post->postCategory)->not->toBeNull();
    expect($post->postCategory->id)->toBe($category->id);

    expect($post->author)->not->toBeNull();
    expect($post->author->id)->toBe($user->id);

    expect($post->user)->not->toBeNull();
    expect($post->user->id)->toBe($user->id);
});

test('products relationship supports contextual commerce with sort order', function () {
    $category = PostCategory::create(['name' => 'Living', 'slug' => 'living']);
    $user = User::factory()->create();

    $ecomCat = Category::create([
        'name' => 'Armchairs',
        'slug' => 'armchairs',
    ]);

    $product1 = Product::create([
        'category_id' => $ecomCat->id,
        'name' => 'Ambit Pendant Lamp',
        'slug' => 'ambit-pendant-lamp',
        'sku' => 'AMB-001',
        'price' => 2500000,
        'stock' => 10,
        'status' => 'published',
    ]);

    $product2 = Product::create([
        'category_id' => $ecomCat->id,
        'name' => 'Synnes Dining Chair',
        'slug' => 'synnes-dining-chair',
        'sku' => 'SYN-002',
        'price' => 4200000,
        'stock' => 5,
        'status' => 'published',
    ]);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Commerce Article',
        'slug' => 'commerce-article',
        'body' => 'Body with commerce products',
        'status' => 'published',
    ]);

    // Attach products with explicit sort order
    $post->products()->attach($product1->id, ['sort_order' => 2]);
    $post->products()->attach($product2->id, ['sort_order' => 1]);

    $attachedProducts = $post->fresh()->products;

    expect($attachedProducts)->toHaveCount(2);
    // Because of orderByPivot('sort_order', 'asc'), product2 (sort_order 1) comes before product1 (sort_order 2)
    expect($attachedProducts->first()->id)->toBe($product2->id);
    expect($attachedProducts->last()->id)->toBe($product1->id);
    expect($attachedProducts->first()->pivot->sort_order)->toBe(1);

    // Test reverse relationship from Product to Post
    $productPosts = $product1->fresh()->posts;
    expect($productPosts)->toHaveCount(1);
    expect($productPosts->first()->id)->toBe($post->id);
    expect($productPosts->first()->pivot->sort_order)->toBe(2);
});

test('featured and banner image url accessors resolve correctly', function () {
    $category = PostCategory::create(['name' => 'Media', 'slug' => 'media']);
    $user = User::factory()->create();

    $postWithExternal = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'External Image Post',
        'slug' => 'ext-post',
        'body' => 'Body',
        'featured_image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc',
        'banner_image' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36',
        'status' => 'published',
    ]);

    expect($postWithExternal->featured_image_url)->toBe('https://images.unsplash.com/photo-1555041469-a586c61ea9bc');
    expect($postWithExternal->banner_image_url)->toBe('https://images.unsplash.com/photo-1524758631624-e2822e304c36');

    $postEmpty = new Post;
    expect($postEmpty->featured_image_url)->toBeNull();
    expect($postEmpty->banner_image_url)->toBeNull();
});
