<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('blog index returns 200 and renders published posts and excludes drafts and future posts', function () {
    $category = PostCategory::create(['name' => 'Xu Hướng Thiết Kế', 'slug' => 'xu-huong-thiet-ke']);
    $user = User::factory()->create(['name' => 'KTS Hoàng Nam']);

    $publishedPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Xu Hướng Nội Thất Scandinavian 2026',
        'slug' => 'xu-huong-noi-that-scandinavian-2026',
        'excerpt' => 'Tổng quan xu hướng thiết kế tối giản Bắc Âu.',
        'body' => '<p>Nội dung chi tiết về phong cách Bắc Âu.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $draftPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bản Thảo Chưa Xuất Bản',
        'slug' => 'ban-thao-chua-xuat-ban',
        'body' => '<p>Bản nháp.</p>',
        'status' => 'draft',
    ]);

    $futurePost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bài Viết Lên Lịch Tương Lai',
        'slug' => 'bai-viet-len-lich-tuong-lai',
        'body' => '<p>Tương lai.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->addDays(5),
    ]);

    $response = $this->get(route('blog.index'));

    $response->assertStatus(200);
    $response->assertSee('Blog &amp; Kiến Thức Nội Thất', false);
    $response->assertSee('Xu Hướng Nội Thất Scandinavian 2026');
    $response->assertDontSee('Bản Thảo Chưa Xuất Bản');
    $response->assertDontSee('Bài Viết Lên Lịch Tương Lai');
});

test('blog index filters posts by category slug', function () {
    $catA = PostCategory::create(['name' => 'Phòng Khách', 'slug' => 'phong-khach']);
    $catB = PostCategory::create(['name' => 'Phòng Bếp', 'slug' => 'phong-bep']);
    $user = User::factory()->create();

    $postA = Post::create([
        'post_category_id' => $catA->id,
        'user_id' => $user->id,
        'title' => 'Bí Quyết Bố Trí Sofa Phòng Khách',
        'slug' => 'bi-quyet-bo-tri-sofa-phong-khach',
        'body' => 'Nội dung phòng khách',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $postB = Post::create([
        'post_category_id' => $catB->id,
        'user_id' => $user->id,
        'title' => 'Mẹo Chọn Bàn Ăn Cho Nhà Bếp',
        'slug' => 'meo-chon-ban-an-cho-nha-bep',
        'body' => 'Nội dung phòng bếp',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $responseA = $this->get(route('blog.index', ['category' => 'phong-khach']));
    $responseA->assertStatus(200);
    $responseA->assertSee('Bí Quyết Bố Trí Sofa Phòng Khách');
    $responseA->assertDontSee('Mẹo Chọn Bàn Ăn Cho Nhà Bếp');

    $responseB = $this->get(route('blog.index', ['category' => 'phong-bep']));
    $responseB->assertStatus(200);
    $responseB->assertSee('Mẹo Chọn Bàn Ăn Cho Nhà Bếp');
    $responseB->assertDontSee('Bí Quyết Bố Trí Sofa Phòng Khách');
});

test('blog index searches posts by keyword', function () {
    $category = PostCategory::create(['name' => 'Kiến Thức', 'slug' => 'kien-thuc']);
    $user = User::factory()->create();

    $post1 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Cách Bảo Quản Gỗ Sồi Tự Nhiên',
        'slug' => 'cach-bao-quan-go-soi-tu-nhien',
        'body' => 'Nội dung về gỗ sồi.',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $post2 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Nghệ Thuật Lựa Chọn Đèn Trang Trí',
        'slug' => 'nghe-thuat-lua-chon-den-trang-tri',
        'body' => 'Nội dung về ánh sáng đèn.',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->get(route('blog.index', ['search' => 'Gỗ Sồi']));

    $response->assertStatus(200);
    $response->assertSee('Cách Bảo Quản Gỗ Sồi Tự Nhiên');
    $response->assertDontSee('Nghệ Thuật Lựa Chọn Đèn Trang Trí');
});

test('blog index renders friendly empty state when no posts match', function () {
    $response = $this->get(route('blog.index', ['search' => 'TừKhóaKhôngTồnTại']));

    $response->assertStatus(200);
    $response->assertSee('Không tìm thấy bài viết nào phù hợp với bộ lọc.');
    $response->assertSee('Xem Tất Cả Bài Viết');
});

test('blog show returns 200 and renders article details with toc for published post', function () {
    $category = PostCategory::create(['name' => 'Cẩm Nang', 'slug' => 'cam-nang']);
    $user = User::factory()->create(['name' => 'KTS Minh Trí']);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Hướng Dẫn Lựa Chọn Bàn Trà',
        'slug' => 'huong-dan-lua-chon-ban-tra',
        'excerpt' => 'Cẩm nang chọn bàn trà hoàn hảo.',
        'body' => '<h2>1. Kích thước tiêu chuẩn</h2><p>Đo đạc diện tích phòng.</p><h3>1.1 Chiều cao bàn trà</h3><p>Chiều cao phù hợp với ghế sofa.</p><h2>2. Chất liệu gỗ</h2><p>Gỗ sồi hoặc gỗ óc chó.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->get(route('blog.show', $post->slug));

    $response->assertStatus(200);
    $response->assertSee('Hướng Dẫn Lựa Chọn Bàn Trà');
    $response->assertSee('KTS Minh Trí');
    $response->assertSee('Cẩm Nang');
    $response->assertSee('Mục Lục Bài Viết');
    $response->assertSee('1. Kích thước tiêu chuẩn');
    $response->assertSee('1.1 Chiều cao bàn trà');
    $response->assertSee('2. Chất liệu gỗ');
    $response->assertSee('id="1-kich-thuoc-tieu-chuan"', false);
    $response->assertSee('id="11-chieu-cao-ban-tra"', false);
    $response->assertSee('id="2-chat-lieu-go"', false);
});

test('blog show returns 404 for draft post to guest user', function () {
    $category = PostCategory::create(['name' => 'Drafts', 'slug' => 'drafts']);
    $user = User::factory()->create();

    $draftPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bản Thảo Chưa Đăng',
        'slug' => 'ban-thao-chua-dang',
        'body' => '<p>Bản thảo bí mật.</p>',
        'status' => 'draft',
    ]);

    $response = $this->get(route('blog.show', $draftPost->slug));
    $response->assertStatus(404);
});

test('blog show returns 200 preview for draft post to authenticated user', function () {
    $category = PostCategory::create(['name' => 'Drafts', 'slug' => 'drafts-auth']);
    $user = User::factory()->create();

    $draftPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bản Thảo Xem Trước',
        'slug' => 'ban-thao-xem-truoc',
        'body' => '<p>Nội dung bản thảo.</p>',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user)->get(route('blog.show', $draftPost->slug));

    $response->assertStatus(200);
    $response->assertSee('Bản Thảo Xem Trước');
    $response->assertSee('Chế độ xem trước');
});

test('blog show renders contextual commerce bottom showcase and sticky sidebar card', function () {
    $category = PostCategory::create(['name' => 'Nội Thất', 'slug' => 'noi-that']);
    $ecomCategory = Category::create(['name' => 'Ghế Đơn', 'slug' => 'ghe-don']);
    $user = User::factory()->create();

    $product1 = Product::create([
        'category_id' => $ecomCategory->id,
        'name' => 'Ghế Armchair Muuto Visu',
        'slug' => 'ghe-armchair-muuto-visu',
        'sku' => 'MUU-001',
        'price' => 5500000,
        'stock' => 8,
        'status' => 'published',
    ]);

    $product2 = Product::create([
        'category_id' => $ecomCategory->id,
        'name' => 'Bàn Trà Around Coffee Table',
        'slug' => 'ban-tra-around-coffee-table',
        'sku' => 'ARO-002',
        'price' => 3800000,
        'stock' => 4,
        'status' => 'published',
    ]);

    $post = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Phối Hợp Ghế Armchair Và Bàn Trà',
        'slug' => 'phoi-hop-ghe-armchair-va-ban-tra',
        'body' => '<p>Hướng dẫn phối hợp không gian sống với ghế armchair và bàn trà.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->subDay(),
    ]);

    $post->products()->attach($product1->id, ['sort_order' => 1]);
    $post->products()->attach($product2->id, ['sort_order' => 2]);

    $response = $this->get(route('blog.show', $post->slug));

    $response->assertStatus(200);
    $response->assertSee('Sản Phẩm Trong Bài Viết');
    $response->assertSee('Ghế Armchair Muuto Visu');
    $response->assertSee('5.500.000₫');
    $response->assertSee('Bàn Trà Around Coffee Table');
    $response->assertSee('3.800.000₫');
    $response->assertSee('Gợi Ý Trong Bài');
});

test('blog show renders related articles from the same category', function () {
    $category = PostCategory::create(['name' => 'Scandinavian', 'slug' => 'scandinavian']);
    $user = User::factory()->create();

    $mainPost = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bài Viết Chính Hiện Tại',
        'slug' => 'bai-viet-chinh-hien-tai',
        'body' => '<p>Nội dung chính.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->subDays(1),
    ]);

    $related1 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bài Viết Liên Quan Thứ Nhất',
        'slug' => 'bai-viet-lien-quan-thu-nhat',
        'body' => '<p>Nội dung liên quan 1.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->subDays(2),
    ]);

    $related2 = Post::create([
        'post_category_id' => $category->id,
        'user_id' => $user->id,
        'title' => 'Bài Viết Liên Quan Thứ Hai',
        'slug' => 'bai-viet-lien-quan-thu-hai',
        'body' => '<p>Nội dung liên quan 2.</p>',
        'status' => 'published',
        'published_at' => Carbon::now()->subDays(3),
    ]);

    $response = $this->get(route('blog.show', $mainPost->slug));

    $response->assertStatus(200);
    $response->assertSee('Bài Viết Cùng Chủ Đề');
    $response->assertSee('Bài Viết Liên Quan Thứ Nhất');
    $response->assertSee('Bài Viết Liên Quan Thứ Hai');
});

test('blog queries execute with optimal performance and zero N+1 queries', function () {
    $category = PostCategory::create(['name' => 'Design', 'slug' => 'design']);
    $ecomCat = Category::create(['name' => 'Chairs', 'slug' => 'chairs']);
    $user = User::factory()->create();

    $products = [];
    for ($i = 1; $i <= 5; $i++) {
        $products[] = Product::create([
            'category_id' => $ecomCat->id,
            'name' => "Product {$i}",
            'slug' => "product-{$i}",
            'sku' => "PRD-00{$i}",
            'price' => 1000000 * $i,
            'stock' => 10,
            'status' => 'published',
        ]);
    }

    $posts = [];
    for ($i = 1; $i <= 10; $i++) {
        $p = Post::create([
            'post_category_id' => $category->id,
            'user_id' => $user->id,
            'title' => "Article {$i}",
            'slug' => "article-{$i}",
            'body' => "<h2>Heading {$i}</h2><p>Content for article {$i}.</p>",
            'status' => 'published',
            'published_at' => Carbon::now()->subHours($i),
        ]);
        $p->products()->attach([$products[0]->id, $products[1]->id]);
        $posts[] = $p;
    }

    // Measure blog.index query count
    DB::flushQueryLog();
    DB::enableQueryLog();

    $responseIndex = $this->get(route('blog.index'));
    $responseIndex->assertStatus(200);

    $indexQueries = DB::getQueryLog();
    expect(count($indexQueries))->toBeLessThanOrEqual(10);

    // Measure blog.show query count
    DB::flushQueryLog();
    DB::enableQueryLog();

    $responseShow = $this->get(route('blog.show', $posts[0]->slug));
    $responseShow->assertStatus(200);

    $showQueries = DB::getQueryLog();
    expect(count($showQueries))->toBeLessThanOrEqual(10);
});
