<?php

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('can create and persist a post category', function () {
    $category = PostCategory::create([
        'name' => 'Kiến Thức Nội Thất',
        'slug' => 'kien-thuc-noi-that',
        'description' => 'Chuyên mục chia sẻ kiến thức về nội thất Scandinavian.',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($category->exists)->toBeTrue();
    expect($category->id)->toBeGreaterThan(0);
    expect($category->name)->toBe('Kiến Thức Nội Thất');
    expect($category->slug)->toBe('kien-thuc-noi-that');
    expect($category->is_active)->toBeTrue();
    expect($category->sort_order)->toBe(1);

    $this->assertDatabaseHas('post_categories', [
        'id' => $category->id,
        'slug' => 'kien-thuc-noi-that',
    ]);
});

test('casts attributes correctly on post category', function () {
    $category = PostCategory::create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'is_active' => 1,
        'sort_order' => '5',
    ]);

    expect($category->is_active)->toBeBool()->toBeTrue();
    expect($category->sort_order)->toBeInt()->toBe(5);
});

test('active scope returns only active categories', function () {
    $active1 = PostCategory::create([
        'name' => 'Active 1',
        'slug' => 'active-1',
        'is_active' => true,
    ]);
    $active2 = PostCategory::create([
        'name' => 'Active 2',
        'slug' => 'active-2',
        'is_active' => true,
    ]);
    $inactive = PostCategory::create([
        'name' => 'Inactive Category',
        'slug' => 'inactive-category',
        'is_active' => false,
    ]);

    $activeCategories = PostCategory::active()->get();

    expect($activeCategories)->toHaveCount(2);
    expect($activeCategories->pluck('id'))->toContain($active1->id, $active2->id);
    expect($activeCategories->pluck('id'))->not->toContain($inactive->id);
});

test('ordered scope orders by sort_order asc then name asc', function () {
    $cat3 = PostCategory::create(['name' => 'Bravo Cat', 'slug' => 'bravo', 'sort_order' => 20]);
    $cat1 = PostCategory::create(['name' => 'Zulu Cat', 'slug' => 'zulu', 'sort_order' => 10]);
    $cat2 = PostCategory::create(['name' => 'Alpha Cat', 'slug' => 'alpha', 'sort_order' => 10]);

    $ordered = PostCategory::ordered()->get();

    expect($ordered->pluck('slug')->toArray())->toEqual(['alpha', 'zulu', 'bravo']);
});

test('posts relationship returns all posts belonging to category', function () {
    $category = PostCategory::create(['name' => 'Living Room', 'slug' => 'living-room']);
    $user = User::factory()->create();

    $post1 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Post 1',
        'slug' => 'post-1',
        'body' => 'Content for post 1',
        'status' => 'published',
    ]);

    $post2 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Post 2',
        'slug' => 'post-2',
        'body' => 'Content for post 2',
        'status' => 'draft',
    ]);

    expect($category->posts)->toHaveCount(2);
    expect($category->posts->pluck('id'))->toContain($post1->id, $post2->id);
});

test('publishedPosts relationship returns only published posts for category', function () {
    $category = PostCategory::create(['name' => 'Living Room', 'slug' => 'living-room-2']);
    $user = User::factory()->create();

    $publishedPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Published Post',
        'slug' => 'published-post',
        'body' => 'Published post content',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $draftPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Draft Post',
        'slug' => 'draft-post',
        'body' => 'Draft post content',
        'status' => 'draft',
    ]);

    $scheduledPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Future Post',
        'slug' => 'future-post',
        'body' => 'Future post content',
        'status' => 'published',
        'published_at' => Carbon::now()->addDays(2),
    ]);

    $publishedPosts = $category->publishedPosts;

    expect($publishedPosts)->toHaveCount(1);
    expect($publishedPosts->first()->id)->toBe($publishedPost->id);
});
