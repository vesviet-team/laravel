<?php

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('blog show renders complete OpenGraph, Twitter card, and canonical metadata', function () {
    $category = PostCategory::create(['name' => 'Thiết Kế', 'slug' => 'thiet-ke']);
    $user = User::factory()->create(['name' => 'Trần Đăng']);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bí Quyết Trang Trí Phòng Ngủ Nhỏ',
        'slug' => 'bi-quyet-trang-tri-phong-ngu-nho',
        'excerpt' => 'Tối ưu không gian phòng ngủ nhỏ với nội thất thông minh.',
        'body' => '<p>Nội dung chi tiết.</p>',
        'featured_image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc',
        'seo_title' => 'Trang Trí Phòng Ngủ Nhỏ Đẹp Chuẩn Bắc Âu | MYSHOP',
        'seo_description' => 'Hướng dẫn decor phòng ngủ diện tích hẹp với tông màu sáng và đồ gỗ tối giản.',
        'canonical_url' => 'https://myshop.vn/blog/bi-quyet-trang-tri-phong-ngu-nho',
        'status' => 'published',
        'published_at' => Carbon::now()->subDays(2),
    ]);

    $response = $this->get(route('blog.show', $post->slug));

    $response->assertStatus(200);

    // OpenGraph assertions
    $response->assertSee('<meta property="og:title" content="Trang Trí Phòng Ngủ Nhỏ Đẹp Chuẩn Bắc Âu | MYSHOP">', false);
    $response->assertSee('<meta property="og:description" content="Hướng dẫn decor phòng ngủ diện tích hẹp với tông màu sáng và đồ gỗ tối giản.">', false);
    $response->assertSee('<meta property="og:type" content="article">', false);
    $response->assertSee('<meta property="og:url" content="'.url()->current().'">', false);
    $response->assertSee('<meta property="og:image" content="https://images.unsplash.com/photo-1555041469-a586c61ea9bc">', false);
    $response->assertSee('<meta property="article:author" content="Trần Đăng">', false);
    $response->assertSee('<meta property="article:section" content="Thiết Kế">', false);

    // Twitter Card assertions
    $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    $response->assertSee('<meta name="twitter:title" content="Trang Trí Phòng Ngủ Nhỏ Đẹp Chuẩn Bắc Âu | MYSHOP">', false);
    $response->assertSee('<meta name="twitter:description" content="Hướng dẫn decor phòng ngủ diện tích hẹp với tông màu sáng và đồ gỗ tối giản.">', false);
    $response->assertSee('<meta name="twitter:image" content="https://images.unsplash.com/photo-1555041469-a586c61ea9bc">', false);

    // Canonical link
    $response->assertSee('<link rel="canonical" href="https://myshop.vn/blog/bi-quyet-trang-tri-phong-ngu-nho">', false);
});

test('blog show renders valid Article / BlogPosting Schema.org JSON-LD', function () {
    $category = PostCategory::create(['name' => 'Xu Hướng', 'slug' => 'xu-huong']);
    $user = User::factory()->create(['name' => 'Lê An']);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Phong Cách Tối Giản Warm Minimalism',
        'slug' => 'phong-cach-toi-gian-warm-minimalism',
        'excerpt' => 'Tìm hiểu phong cách Warm Minimalism.',
        'body' => '<p>Chi tiết Warm Minimalism.</p>',
        'featured_image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7',
        'schema_type' => 'BlogPosting',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->get(route('blog.show', $post->slug));
    $response->assertStatus(200);

    $html = $response->getContent();
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    expect($matches[1])->not->toBeEmpty();

    $schemas = array_map(fn ($json) => json_decode(trim($json), true), $matches[1]);
    $blogPostingSchema = collect($schemas)->firstWhere('@type', 'BlogPosting');

    expect($blogPostingSchema)->not->toBeNull();
    expect($blogPostingSchema['@context'])->toBe('https://schema.org');
    expect($blogPostingSchema['headline'])->toBe('Phong Cách Tối Giản Warm Minimalism');
    expect($blogPostingSchema['author']['name'])->toBe('Lê An');
    expect($blogPostingSchema['publisher']['name'])->toBe('MYSHOP');
    expect($blogPostingSchema['image'])->toContain('https://images.unsplash.com/photo-1586023492125-27b2c045efd7');
});

test('blog show renders valid BreadcrumbList Schema.org JSON-LD', function () {
    $category = PostCategory::create(['name' => 'Cẩm Nang', 'slug' => 'cam-nang']);
    $user = User::factory()->create();

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Vệ Sinh Sofa Vải Đúng Cách',
        'slug' => 've-sinh-sofa-vai-dung-cach',
        'body' => '<p>Nội dung sofa.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->get(route('blog.show', $post->slug));
    $response->assertStatus(200);

    $html = $response->getContent();
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    $schemas = array_map(fn ($json) => json_decode(trim($json), true), $matches[1]);
    $breadcrumbSchema = collect($schemas)->firstWhere('@type', 'BreadcrumbList');

    expect($breadcrumbSchema)->not->toBeNull();
    expect($breadcrumbSchema['itemListElement'])->toHaveCount(4);

    expect($breadcrumbSchema['itemListElement'][0]['name'])->toBe('Trang Chủ');
    expect($breadcrumbSchema['itemListElement'][0]['position'])->toBe(1);

    expect($breadcrumbSchema['itemListElement'][1]['name'])->toBe('Blog');
    expect($breadcrumbSchema['itemListElement'][1]['position'])->toBe(2);

    expect($breadcrumbSchema['itemListElement'][2]['name'])->toBe('Cẩm Nang');
    expect($breadcrumbSchema['itemListElement'][2]['position'])->toBe(3);

    expect($breadcrumbSchema['itemListElement'][3]['name'])->toBe('Vệ Sinh Sofa Vải Đúng Cách');
    expect($breadcrumbSchema['itemListElement'][3]['position'])->toBe(4);
});

test('blog show conditionally renders FAQPage Schema.org JSON-LD when faq_schema is present', function () {
    $category = PostCategory::create(['name' => 'Hỏi Đáp', 'slug' => 'hoi-dap']);
    $user = User::factory()->create();

    $faqData = [
        [
            'question' => 'Nên bảo dưỡng bàn ăn gỗ sồi bao lâu một lần?',
            'answer' => 'Nên lau dầu bảo dưỡng định kỳ 6 tháng một lần.',
        ],
        [
            'question' => 'Có nên để đồ nóng trực tiếp lên mặt bàn gỗ?',
            'answer' => 'Không nên, hãy luôn sử dụng lót ly hoặc đế lót nhiệt.',
        ],
    ];

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Cách Chăm Sóc Đồ Gỗ',
        'slug' => 'cach-cham-soc-do-go',
        'body' => '<p>Hướng dẫn bảo quản gỗ.</p>',
        'faq_schema' => $faqData,
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->get(route('blog.show', $post->slug));
    $response->assertStatus(200);

    $html = $response->getContent();
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    $schemas = array_map(fn ($json) => json_decode(trim($json), true), $matches[1]);
    $faqSchema = collect($schemas)->firstWhere('@type', 'FAQPage');

    expect($faqSchema)->not->toBeNull();
    expect($faqSchema['mainEntity'])->toHaveCount(2);
    expect($faqSchema['mainEntity'][0]['name'])->toBe('Nên bảo dưỡng bàn ăn gỗ sồi bao lâu một lần?');
    expect($faqSchema['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Nên lau dầu bảo dưỡng định kỳ 6 tháng một lần.');
    expect($faqSchema['mainEntity'][1]['name'])->toBe('Có nên để đồ nóng trực tiếp lên mặt bàn gỗ?');
});

test('blog show omits FAQPage Schema.org JSON-LD when faq_schema is empty or null', function () {
    $category = PostCategory::create(['name' => 'General', 'slug' => 'general']);
    $user = User::factory()->create();

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bài Viết Không Có FAQ',
        'slug' => 'bai-viet-khong-co-faq',
        'body' => '<p>Bài viết thông thường không có phần FAQ.</p>',
        'faq_schema' => null,
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->get(route('blog.show', $post->slug));
    $response->assertStatus(200);

    $html = $response->getContent();
    expect($html)->not->toContain('"@type": "FAQPage"');
});

test('static page renders OpenGraph, WebPage and BreadcrumbList JSON-LD metadata', function () {
    $page = Page::create([
        'title' => 'Điều Khoản Dịch Vụ',
        'slug' => 'dieu-khoan-dich-vu',
        'excerpt' => 'Quy định sử dụng dịch vụ tại Sober Furniture.',
        'body' => '<p>Điều khoản chi tiết.</p>',
        'seo_title' => 'Điều Khoản & Điều Kiện Giao Dịch | MYSHOP',
        'seo_description' => 'Các quy định mua hàng, thanh toán và bảo hành tại MYSHOP.',
        'canonical_url' => 'https://myshop.vn/dieu-khoan-dich-vu',
        'is_published' => true,
    ]);

    $response = $this->get('/dieu-khoan-dich-vu');

    $response->assertStatus(200);

    $response->assertSee('<meta property="og:title" content="Điều Khoản &amp; Điều Kiện Giao Dịch | MYSHOP">', false);
    $response->assertSee('<link rel="canonical" href="https://myshop.vn/dieu-khoan-dich-vu">', false);

    $html = $response->getContent();
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    $schemas = array_map(fn ($json) => json_decode(trim($json), true), $matches[1]);
    $webPageSchema = collect($schemas)->firstWhere('@type', 'WebPage');
    $breadcrumbSchema = collect($schemas)->firstWhere('@type', 'BreadcrumbList');

    expect($webPageSchema)->not->toBeNull();
    expect($webPageSchema['name'])->toBe('Điều Khoản Dịch Vụ');
    expect($breadcrumbSchema)->not->toBeNull();
    expect($breadcrumbSchema['itemListElement'])->toHaveCount(2);
});
