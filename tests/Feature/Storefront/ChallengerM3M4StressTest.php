<?php

use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\TocService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('Challenger M3 & M4 Empirical Stress Tests', function () {

    test('Empirical JSON-LD schema parsing stress test with adversarial characters, double/single quotes, ampersands, backslashes, and Vietnamese diacritics', function () {
        $category = PostCategory::create(['name' => 'Adversarial & "Quotes" & \'Single\'', 'slug' => 'adversarial-quotes']);
        $user = User::factory()->create(['name' => 'Tác Giả "Đặc Biệt" (Senior Architect)']);

        $adversarialTitle = 'Nội Thất "Bắc Âu" & Hiện Đại: Bí Quyết \'Decor\' Nhà Đẹp (2026) — Gỗ Tự Nhiên & Đá Marble';
        $adversarialExcerpt = 'Hướng dẫn "tối ưu" không gian với đồ gỗ sồi / walnut & đèn chùm; \ "backslash" & 100% tự nhiên';
        $adversarialBody = <<<'HTML'
        <h2>1. Giới thiệu "Không Gian Sống" & Phong Thủy</h2>
        <p>Chi tiết phần 1 với ký tự đặc biệt: "quote", 'single', &amp; ampersand, / slash, \ backslash.</p>
        <h3>1.1. Chi tiết &lt;Phong Cách&gt; "Minimalism"</h3>
        <p>Chi tiết phần 1.1.</p>
        <h2>2. Sản phẩm & Lựa chọn</h2>
        <p>Chi tiết phần 2.</p>
        HTML;

        $adversarialFaq = [
            [
                'question' => 'Làm sao để bảo quản "Gỗ Tự Nhiên" & chống mối mọt trong môi trường ẩm?',
                'answer' => "Sử dụng dầu lau thực vật & lau khô bề mặt ngay khi dính nước. Không dùng cồn 90° hoặc axit mạnh.\nXuống dòng và dấu ngoặc kép \"OK\".",
            ],
            [
                'q' => 'Sản phẩm có hỗ trợ bảo hành tại "TP. Hồ Chí Minh" & Hà Nội không?',
                'a' => 'Có, MYSHOP hỗ trợ bảo hành 24/7 trên toàn quốc (hotline: 1900-xxxx). Đường dẫn: https://myshop.vn/policy?ref=faq&lang=vi',
            ],
        ];

        $post = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $user->id,
            'title' => $adversarialTitle,
            'slug' => 'adversarial-stress-post-slug',
            'excerpt' => $adversarialExcerpt,
            'body' => $adversarialBody,
            'seo_title' => 'SEO: '.$adversarialTitle,
            'seo_description' => 'SEO Desc: '.$adversarialExcerpt,
            'canonical_url' => 'https://myshop.vn/blog/adversarial-stress-post-slug?param=1&flag=true',
            'faq_schema' => $adversarialFaq,
            'schema_type' => 'Article',
            'status' => 'published',
            'published_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->get(route('blog.show', $post->slug));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Extract all ld+json blocks
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        expect($matches[1])->toHaveCount(3); // Article, BreadcrumbList, FAQPage

        foreach ($matches[1] as $index => $jsonString) {
            $trimmed = trim($jsonString);
            $decoded = json_decode($trimmed, true);

            // Assert JSON is strictly valid RFC 8259 without parsing errors
            expect(json_last_error())->toBe(JSON_ERROR_NONE, "JSON-LD index {$index} failed parsing: ".json_last_error_msg());
            expect($decoded)->toBeArray();
            expect($decoded['@context'])->toBe('https://schema.org');
        }

        // Verify Article schema details
        $schemas = array_map(fn ($j) => json_decode(trim($j), true), $matches[1]);
        $article = collect($schemas)->firstWhere('@type', 'Article');
        expect($article)->not->toBeNull();
        expect($article['headline'])->toBe($adversarialTitle);
        expect($article['description'])->toBe('SEO Desc: '.$adversarialExcerpt);
        expect($article['author']['name'])->toBe('Tác Giả "Đặc Biệt" (Senior Architect)');

        // Verify Breadcrumb schema
        $breadcrumbs = collect($schemas)->firstWhere('@type', 'BreadcrumbList');
        expect($breadcrumbs)->not->toBeNull();
        expect($breadcrumbs['itemListElement'])->toHaveCount(4);
        expect($breadcrumbs['itemListElement'][2]['name'])->toBe('Adversarial & "Quotes" & \'Single\'');
        expect($breadcrumbs['itemListElement'][3]['name'])->toBe($adversarialTitle);

        // Verify FAQ schema
        $faq = collect($schemas)->firstWhere('@type', 'FAQPage');
        expect($faq)->not->toBeNull();
        expect($faq['mainEntity'])->toHaveCount(2);
        expect($faq['mainEntity'][0]['name'])->toBe('Làm sao để bảo quản "Gỗ Tự Nhiên" & chống mối mọt trong môi trường ẩm?');
        expect($faq['mainEntity'][0]['acceptedAnswer']['text'])->toContain('dầu lau thực vật');
        expect($faq['mainEntity'][1]['name'])->toBe('Sản phẩm có hỗ trợ bảo hành tại "TP. Hồ Chí Minh" & Hà Nội không?');
    });

    test('Empirical Page JSON-LD schema parsing stress test with adversarial inputs and policy template', function () {
        $page = Page::create([
            'title' => 'Chính Sách & Quy Định "Bảo Hành" VIP (2026)',
            'slug' => 'chinh-sach-bao-hanh-vip',
            'excerpt' => 'Quy định bảo hành "1 đổi 1" trong vòng 30 ngày đối với lỗi từ nhà sản xuất.',
            'body' => '<h2>1. Phạm Vi Áp Dụng</h2><p>Áp dụng cho toàn bộ "sản phẩm" & phụ kiện.</p>',
            'seo_title' => 'Chính Sách "Bảo Hành" VIP 2026 | MYSHOP',
            'seo_description' => 'Chi tiết chính sách bảo hành & đổi trả tận nơi.',
            'canonical_url' => 'https://myshop.vn/chinh-sach-bao-hanh-vip',
            'template' => 'policy',
            'is_published' => true,
            'faq_schema' => [
                [
                    'question' => 'Quy trình đổi trả diễn ra trong bao lâu?',
                    'answer' => 'Từ 2 - 5 ngày làm việc kể từ khi nhận được yêu cầu.',
                ],
            ],
        ]);

        $response = $this->get('/chinh-sach-bao-hanh-vip');
        $response->assertStatus(200);

        $html = $response->getContent();
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        expect($matches[1])->toHaveCount(3); // WebPage, BreadcrumbList, FAQPage

        foreach ($matches[1] as $index => $jsonString) {
            $trimmed = trim($jsonString);
            $decoded = json_decode($trimmed, true);

            expect(json_last_error())->toBe(JSON_ERROR_NONE, "Page JSON-LD index {$index} failed parsing: ".json_last_error_msg());
            expect($decoded)->toBeArray();
            expect($decoded['@context'])->toBe('https://schema.org');
        }

        $schemas = array_map(fn ($j) => json_decode(trim($j), true), $matches[1]);
        $webPage = collect($schemas)->firstWhere('@type', 'WebPage');
        expect($webPage['name'])->toBe('Chính Sách & Quy Định "Bảo Hành" VIP (2026)');

        $breadcrumb = collect($schemas)->firstWhere('@type', 'BreadcrumbList');
        expect($breadcrumb['itemListElement'])->toHaveCount(2);
        expect($breadcrumb['itemListElement'][1]['name'])->toBe('Chính Sách & Quy Định "Bảo Hành" VIP (2026)');

        $faq = collect($schemas)->firstWhere('@type', 'FAQPage');
        expect($faq['mainEntity'])->toHaveCount(1);
        expect($faq['mainEntity'][0]['name'])->toBe('Quy trình đổi trả diễn ra trong bao lâu?');
    });

    test('TocService Adversarial Matrix: duplicate titles, extreme diacritics, empty headings, attributes', function () {
        $tocService = new TocService;

        // 1. 20 duplicate headings stress test
        $duplicateHtml = '';
        for ($i = 0; $i < 20; $i++) {
            $duplicateHtml .= "<h2>Tiêu Đề Trùng Lặp</h2><p>Nội dung {$i}</p>";
        }
        $dupResult = $tocService->generate($duplicateHtml);
        expect($dupResult['toc'])->toHaveCount(20);
        expect($dupResult['toc'][0]['id'])->toBe('tieu-de-trung-lap');
        expect($dupResult['toc'][1]['id'])->toBe('tieu-de-trung-lap-1');
        expect($dupResult['toc'][19]['id'])->toBe('tieu-de-trung-lap-19');

        // 2. Empty headings & whitespace headings ignored
        $emptyHtml = '<h2></h2><h3>   </h3><h2><span></span></h2><h2>Hợp Lệ</h2>';
        $emptyResult = $tocService->generate($emptyHtml);
        expect($emptyResult['toc'])->toHaveCount(1);
        expect($emptyResult['toc'][0]['title'])->toBe('Hợp Lệ');

        // 3. Headings with existing attributes & existing IDs
        $attrHtml = '<h2 class="font-bold uppercase" id="old-custom-id" data-anchor="intro" style="color:red">Tiêu Đề Có Thuộc Tính</h2>';
        $attrResult = $tocService->generate($attrHtml);
        expect($attrResult['toc'][0]['id'])->toBe('tieu-de-co-thuoc-tinh');
        expect($attrResult['html'])->toContain('id="tieu-de-co-thuoc-tinh"');
        expect($attrResult['html'])->toContain('class="font-bold uppercase"');
        expect($attrResult['html'])->toContain('data-anchor="intro"');
        expect($attrResult['html'])->toContain('style="color:red"');
        expect($attrResult['html'])->not->toContain('id="old-custom-id"');

        // 4. Non-Latin / Special symbols / Emojis / Numbers
        $specialHtml = '<h2>🌿 100% Gỗ Tự Nhiên &amp; Chứng Chỉ FSC (2026) ⭐</h2><h3>#1. Xu Hướng Mới!</h3>';
        $specialResult = $tocService->generate($specialHtml);
        expect($specialResult['toc'])->toHaveCount(2);
        expect($specialResult['toc'][0]['title'])->toBe('🌿 100% Gỗ Tự Nhiên &amp; Chứng Chỉ FSC (2026) ⭐');
        expect($specialResult['toc'][1]['title'])->toBe('#1. Xu Hướng Mới!');
        expect($specialResult['toc'][0]['id'])->not->toBeEmpty();
        expect($specialResult['toc'][1]['id'])->not->toBeEmpty();

        // 5. Multi-line heading & nested tags
        $multilineHtml = <<<'HTML'
        <h2 class="title">
            <span>Phong Cách</span>
            <strong>Scandinavian</strong>
        </h2>
        HTML;
        $multiResult = $tocService->generate($multilineHtml);
        expect($multiResult['toc'])->toHaveCount(1);
        expect($multiResult['toc'][0]['id'])->toBe('phong-cach-scandinavian');

        // 6. High-volume stress test: 200 headings
        $largeHtml = '';
        for ($i = 1; $i <= 200; $i++) {
            $tag = ($i % 2 === 0) ? 'h2' : 'h3';
            $largeHtml .= "<{$tag}>Mục Thứ {$i} Trong Bài</{$tag}><p>Đoạn văn {$i}</p>";
        }
        $largeResult = $tocService->generate($largeHtml);
        expect($largeResult['toc'])->toHaveCount(200);
        expect($largeResult['toc'][0]['title'])->toBe('Mục Thứ 1 Trong Bài');
        expect($largeResult['toc'][199]['title'])->toBe('Mục Thứ 200 Trong Bài');
    });

    test('Storefront routes adversarial and boundary behavior', function () {
        // Test non-existent blog post returns 404
        $resBlog404 = $this->get(route('blog.show', 'non-existent-blog-slug-999'));
        $resBlog404->assertStatus(404);

        // Test non-existent page returns 404
        $resPage404 = $this->get('/non-existent-page-slug-999');
        $resPage404->assertStatus(404);

        // Test blog index with SQL wildcard characters in search query
        $resSearchWildcard = $this->get(route('blog.index', ['search' => '%_--\'"\\']));
        $resSearchWildcard->assertStatus(200);

        // Test blog index with category filter for non-existent category returns 200 with empty list
        $resCatNonExistent = $this->get(route('blog.index', ['category' => 'category-does-not-exist-xyz']));
        $resCatNonExistent->assertStatus(200);
        $resCatNonExistent->assertSee('Không tìm thấy bài viết nào phù hợp với bộ lọc.');
    });

    test('Blog show handles empty category gracefully in breadcrumb schema', function () {
        $user = User::factory()->create();
        $postNoCategory = Post::create([
            'post_category_id' => null,
            'user_id' => $user->id,
            'title' => 'Bài Viết Không Có Chuyên Mục',
            'slug' => 'bai-viet-khong-co-chuyen-muc',
            'body' => '<h2>Nội Dung</h2><p>Mô tả bài viết.</p>',
            'status' => 'published',
            'published_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->get(route('blog.show', $postNoCategory->slug));
        $response->assertStatus(200);

        $html = $response->getContent();
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

        $schemas = array_map(fn ($j) => json_decode(trim($j), true), $matches[1]);
        $breadcrumb = collect($schemas)->firstWhere('@type', 'BreadcrumbList');

        expect($breadcrumb)->not->toBeNull();
        expect($breadcrumb['itemListElement'])->toHaveCount(3);
        expect($breadcrumb['itemListElement'][0]['name'])->toBe('Trang Chủ');
        expect($breadcrumb['itemListElement'][1]['name'])->toBe('Blog');
        expect($breadcrumb['itemListElement'][2]['name'])->toBe('Bài Viết Không Có Chuyên Mục');
    });

    test('Blog show with products attached renders contextual commerce in HTML without breaking schema', function () {
        $category = PostCategory::create(['name' => 'Phòng Khách', 'slug' => 'phong-khach-contextual']);
        $ecomCat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $user = User::factory()->create();

        $product = Product::create([
            'category_id' => $ecomCat->id,
            'name' => 'Ghế Lounge Eames & Ottoman',
            'slug' => 'ghe-lounge-eames-ottoman',
            'sku' => 'EAM-001',
            'price' => 18000000,
            'stock' => 3,
            'status' => 'published',
        ]);

        $post = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $user->id,
            'title' => 'Tác Phẩm Biểu Tượng Eames Lounge',
            'slug' => 'tac-pham-bieu-tuong-eames-lounge',
            'body' => '<h2>Lịch sử ra đời</h2><p>Chi tiết về Eames Lounge.</p>',
            'status' => 'published',
            'published_at' => Carbon::now()->subDay(),
        ]);

        $post->products()->attach($product->id, ['sort_order' => 1]);

        $response = $this->get(route('blog.show', $post->slug));
        $response->assertStatus(200);
        $response->assertSee('Ghế Lounge Eames &amp; Ottoman', false);
        $response->assertSee('18.000.000₫');
        $response->assertSee('Gợi Ý Trong Bài');
    });

    test('Blog show future scheduled post returns 404 for guest and 200 preview banner for authenticated user', function () {
        $category = PostCategory::create(['name' => 'Future Posts', 'slug' => 'future-posts']);
        $user = User::factory()->create();

        $futurePost = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $user->id,
            'title' => 'Bài Viết Lên Lịch Tháng Sau',
            'slug' => 'bai-viet-len-lich-thang-sau',
            'body' => '<h2>Nội Dung Tương Lai</h2><p>Chưa đến ngày công bố.</p>',
            'status' => 'published',
            'published_at' => Carbon::now()->addDays(30),
        ]);

        // Guest gets 404
        $guestRes = $this->get(route('blog.show', $futurePost->slug));
        $guestRes->assertStatus(404);

        // Authenticated user gets 200 preview
        $authRes = $this->actingAs($user)->get(route('blog.show', $futurePost->slug));
        $authRes->assertStatus(200);
        $authRes->assertSee('Chế độ xem trước');
        $authRes->assertSee('Bài Viết Lên Lịch Tháng Sau');
    });

    test('Dynamic page controller fallback accurately displays active LandingPage and 404 for inactive LandingPage', function () {
        $category = Category::create(['name' => 'Bàn', 'slug' => 'ban-landing']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Bàn Gỗ Sồi Bắc Âu Pro',
            'slug' => 'ban-go-soi-bac-au-pro',
            'sku' => 'BAN-PRO-01',
            'price' => 15000000,
            'stock' => 10,
            'status' => 'published',
        ]);

        $activeLanding = LandingPage::create([
            'title' => 'Landing Page Khuyến Mãi Hè',
            'slug' => 'khuyen-mai-he-2026',
            'product_id' => $product->id,
            'is_active' => true,
            'features_json' => ['Feature A', 'Feature B'],
            'header_cta_text' => 'Mua Ngay',
        ]);

        $inactiveLanding = LandingPage::create([
            'title' => 'Landing Page Đã Đóng',
            'slug' => 'khuyen-mai-da-dong',
            'product_id' => $product->id,
            'is_active' => false,
            'features_json' => ['Feature C'],
            'header_cta_text' => 'Hết Hạn',
        ]);

        // Active landing page loads
        $activeRes = $this->get('/khuyen-mai-he-2026');
        $activeRes->assertStatus(200);
        $activeRes->assertSee('Landing Page Khuyến Mãi Hè');
        $activeRes->assertSee('Feature A');

        // Inactive landing page returns 404
        $inactiveRes = $this->get('/khuyen-mai-da-dong');
        $inactiveRes->assertStatus(404);
    });

});
