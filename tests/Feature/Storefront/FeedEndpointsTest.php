<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Living Room',
        'slug' => 'living-room',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'name' => 'Minimalist Sofa',
        'slug' => 'minimalist-sofa',
        'price' => 12000000,
        'stock' => 5,
        'category_id' => $this->category->id,
        'status' => 'published',
        'image_path' => 'products/sofa.jpg',
    ]);

    $this->post = Post::create([
        'title' => 'Top Interior Trends 2026',
        'slug' => 'top-interior-trends-2026',
        'body' => '<p>Trend overview</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->page = Page::create([
        'title' => 'Privacy Policy',
        'slug' => 'privacy-policy',
        'content' => '<p>Privacy policy content</p>',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);
});

it('returns valid sitemap index XML with correct headers', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())->toContain('<sitemapindex');
    expect($response->getContent())->toContain(url('/sitemap-products.xml'));
    expect($response->getContent())->toContain(url('/sitemap-posts.xml'));
});

it('returns products sitemap with image extension tags', function () {
    $response = $this->get('/sitemap-products.xml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())->toContain('<urlset');
    expect($response->getContent())->toContain(route('products.show', $this->product->slug));
    expect($response->getContent())->toContain('<image:loc>');
    expect($response->getContent())->toContain('Minimalist Sofa');
});

it('returns categories sitemap', function () {
    $response = $this->get('/sitemap-categories.xml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())->toContain('<urlset');
    expect($response->getContent())->toContain(route('products.index', ['category' => $this->category->slug]));
});

it('returns blog posts sitemap', function () {
    $response = $this->get('/sitemap-posts.xml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())->toContain('<urlset');
    expect($response->getContent())->toContain(route('blog.show', $this->post->slug));
});

it('returns static and cms pages sitemap', function () {
    $response = $this->get('/sitemap-pages.xml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())->toContain('<urlset');
    expect($response->getContent())->toContain(route('page.show', $this->page->slug));
    expect($response->getContent())->toContain(route('products.index'));
});

it('returns Google Merchant Center XML feed conforming to schema', function () {
    $response = $this->get('/feeds/google-merchant.xml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())->toContain('<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"');
    expect($response->getContent())->toContain('<g:id>'.$this->product->id.'</g:id>');
    expect($response->getContent())->toContain('<g:title>Minimalist Sofa</g:title>');
    expect($response->getContent())->toContain('<g:availability>in_stock</g:availability>');
    expect($response->getContent())->toContain('<g:price>12000000 VND</g:price>');
});

it('returns Blog RSS 2.0 feed', function () {
    $response = $this->get('/feed');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())->toContain('<rss version="2.0"');
    expect($response->getContent())->toContain('<title>Top Interior Trends 2026</title>');
    expect($response->getContent())->toContain(route('blog.show', $this->post->slug));
});
