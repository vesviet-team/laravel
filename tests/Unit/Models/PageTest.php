<?php

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('can create and persist a static policy page', function () {
    $page = Page::create([
        'title' => 'Chính Sách Bảo Mật',
        'slug' => 'chinh-sach-bao-mat',
        'excerpt' => 'Chính sách bảo mật thông tin khách hàng tại Sober Furniture.',
        'body' => '<h2>1. Thu thập thông tin</h2><p>Chúng tôi cam kết bảo mật tuyệt đối thông tin cá nhân của quý khách.</p>',
        'featured_image' => 'pages/privacy-banner.jpg',
        'is_published' => true,
        'template' => 'policy',
        'meta_title' => 'Chính Sách Bảo Mật | Sober Furniture',
        'meta_description' => 'Tìm hiểu chính sách bảo mật và quyền riêng tư tại Sober Furniture.',
        'canonical_url' => 'https://soberfurniture.vn/chinh-sach-bao-mat',
        'schema_type' => 'WebPage',
        'faq_schema' => [
            [
                'question' => 'Thông tin của tôi có bị chia sẻ cho bên thứ ba không?',
                'answer' => 'Không, chúng tôi chỉ sử dụng thông tin để xử lý đơn hàng và giao hàng.',
            ],
        ],
    ]);

    expect($page->exists)->toBeTrue();
    expect($page->id)->toBeGreaterThan(0);
    expect($page->title)->toBe('Chính Sách Bảo Mật');
    expect($page->slug)->toBe('chinh-sach-bao-mat');
    expect($page->template)->toBe('policy');
    expect($page->is_published)->toBeTrue();
    expect($page->faq_schema)->toBeArray();
    expect($page->faq_schema[0]['question'])->toBe('Thông tin của tôi có bị chia sẻ cho bên thứ ba không?');

    $this->assertDatabaseHas('pages', [
        'id' => $page->id,
        'slug' => 'chinh-sach-bao-mat',
    ]);
});

test('casts attributes correctly on page', function () {
    $page = Page::create([
        'title' => 'Terms of Service',
        'slug' => 'terms-of-service',
        'body' => 'Terms content',
        'is_published' => 1,
        'published_at' => '2026-08-19 10:00:00',
        'faq_schema' => [['q' => 'Test', 'a' => 'Answer']],
    ]);

    expect($page->is_published)->toBeBool()->toBeTrue();
    expect($page->published_at)->toBeInstanceOf(Carbon::class);
    expect($page->faq_schema)->toBeArray();
});

test('scopePublished filters only published pages', function () {
    $publishedWithNoDate = Page::create([
        'title' => 'Page 1',
        'slug' => 'page-1',
        'body' => 'Body 1',
        'is_published' => true,
        'published_at' => null,
    ]);

    $publishedWithPastDate = Page::create([
        'title' => 'Page 2',
        'slug' => 'page-2',
        'body' => 'Body 2',
        'is_published' => true,
        'published_at' => Carbon::now()->subDay(),
    ]);

    $futurePage = Page::create([
        'title' => 'Future Page',
        'slug' => 'future-page',
        'body' => 'Body future',
        'is_published' => true,
        'published_at' => Carbon::now()->addDays(2),
    ]);

    $unpublishedPage = Page::create([
        'title' => 'Unpublished Page',
        'slug' => 'unpublished-page',
        'body' => 'Body unpublished',
        'is_published' => false,
    ]);

    $publishedPages = Page::published()->get();

    expect($publishedPages)->toHaveCount(2);
    expect($publishedPages->pluck('id'))->toContain($publishedWithNoDate->id, $publishedWithPastDate->id);
    expect($publishedPages->pluck('id'))->not->toContain($futurePage->id, $unpublishedPage->id);
});

test('content and body accessors and mutators work seamlessly for page', function () {
    $page = new Page;
    $page->content = '<p>Page Content Body</p>';

    expect($page->body)->toBe('<p>Page Content Body</p>');
    expect($page->content)->toBe('<p>Page Content Body</p>');

    $page->body = '<p>Updated Page Body</p>';
    expect($page->content)->toBe('<p>Updated Page Body</p>');
});

test('featured_image_url resolves correctly for page', function () {
    $page = Page::create([
        'title' => 'Page with Image',
        'slug' => 'page-image',
        'body' => 'Body',
        'featured_image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7',
    ]);

    expect($page->featured_image_url)->toBe('https://images.unsplash.com/photo-1586023492125-27b2c045efd7');

    $pageWithoutImage = new Page;
    expect($pageWithoutImage->featured_image_url)->toBeNull();
});
