<?php

use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('static page returns 200 and renders title and body for published page', function () {
    $page = Page::create([
        'title' => 'Chính Sách Bảo Mật Thông Tin',
        'slug' => 'chinh-sach-bao-mat',
        'excerpt' => 'Quy định bảo vệ dữ liệu khách hàng.',
        'body' => '<h2>1. Mục đích thu thập</h2><p>Chúng tôi tôn trọng quyền riêng tư của quý khách.</p>',
        'is_published' => true,
        'template' => 'policy',
    ]);

    $response = $this->get('/chinh-sach-bao-mat');

    $response->assertStatus(200);
    $response->assertSee('Chính Sách Bảo Mật Thông Tin');
    $response->assertSee('Quy định bảo vệ dữ liệu khách hàng.');
    $response->assertSee('1. Mục đích thu thập');
    $response->assertSee('Chính Sách &amp; Điều Khoản', false);
});

test('static page returns 404 for draft or unpublished page', function () {
    $unpublishedPage = Page::create([
        'title' => 'Trang Nháp Chưa Đăng',
        'slug' => 'trang-nhap-chua-dang',
        'body' => '<p>Nội dung nháp.</p>',
        'is_published' => false,
    ]);

    $response = $this->get('/trang-nhap-chua-dang');
    $response->assertStatus(404);
});

test('static page falls back to active landing page if not found in page table', function () {
    $category = Category::create(['name' => 'Bàn Ăn', 'slug' => 'ban-an']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Bàn Ăn 70/70 Table',
        'slug' => 'ban-an-70-70-table',
        'sku' => 'BAN-070',
        'price' => 12500000,
        'stock' => 5,
        'status' => 'published',
    ]);

    $landingPage = LandingPage::create([
        'title' => 'Ưu Đãi Bàn Ăn Cao Cấp',
        'slug' => 'uu-dai-ban-an-cao-cap',
        'product_id' => $product->id,
        'is_active' => true,
        'features_json' => ['Gỗ sồi nhập khẩu', 'Bảo hành 5 năm'],
        'header_cta_text' => 'Đặt Hàng Ngay',
    ]);

    $response = $this->get('/uu-dai-ban-an-cao-cap');

    $response->assertStatus(200);
    $response->assertSee('Ưu Đãi Bàn Ăn Cao Cấp');
    $response->assertSee('Gỗ sồi nhập khẩu');
    $response->assertSee('12.500.000₫');
});

test('returns 404 when slug matches neither page nor active landing page', function () {
    $response = $this->get('/duong-dan-khong-ton-tai-12345');
    $response->assertStatus(404);
});

test('static page does not intercept or break dedicated system routes', function () {
    $responseAbout = $this->get('/about');
    $responseAbout->assertStatus(200);

    $responseContact = $this->get('/contact');
    $responseContact->assertStatus(200);

    $responseProducts = $this->get('/products');
    $responseProducts->assertStatus(200);

    $responseBlog = $this->get('/blog');
    $responseBlog->assertStatus(200);

    $responseTrackOrder = $this->get('/track-order');
    $responseTrackOrder->assertStatus(200);

    $responseCheckout = $this->get('/checkout');
    $this->assertTrue(in_array($responseCheckout->status(), [200, 302]));
});
