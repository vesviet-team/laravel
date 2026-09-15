<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds for Scandinavian furniture banners.
     */
    public function run(): void
    {
        $banners = [
            // ── 1. Hero Slides (hero_slider) ──────────────────────────────────
            [
                'position' => Banner::POSITION_HERO_SLIDER,
                'title' => 'Bộ Sưu Tập Bắc Âu 2026',
                'eyebrow' => 'SCANDINAVIAN MINIMALISM',
                'subtitle' => 'Tinh hoa nội thất gỗ sồi tự nhiên mang đến vẻ đẹp tối giản, ấm cúng và trường tồn cho không gian sống hiện đại.',
                'cta_text' => 'Khám Phá Ngay',
                'link' => '/catalog?category=living-room',
                'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600&auto=format&fit=crop&q=80',
                'open_in_new_tab' => false,
                'sort_order' => 1,
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
                'clicks_count' => 0,
            ],
            [
                'position' => Banner::POSITION_HERO_SLIDER,
                'title' => 'Đèn Thả & Ghế Thư Giãn Tinh Tế',
                'eyebrow' => 'PREMIUM LIGHTING & SEATING',
                'subtitle' => 'Ánh sáng dịu nhẹ và đường nét thanh thoát nâng tầm đẳng cấp căn phòng của bạn.',
                'cta_text' => 'Xem Bộ Sưu Tập',
                'link' => '/catalog?category=lighting',
                'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=1600&auto=format&fit=crop&q=80',
                'open_in_new_tab' => false,
                'sort_order' => 2,
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
                'clicks_count' => 0,
            ],

            // ── 2. Khuyến Mãi 2 Cột (home_promo_2col) ──────────────────────────
            [
                'position' => Banner::POSITION_HOME_PROMO_2COL,
                'title' => 'Ưu Đãi Mùa Hè 20%',
                'eyebrow' => 'SUMMER COLLECTION · BÀN ĂN',
                'subtitle' => 'Giảm giá đặc biệt cho các mẫu bàn ăn & ghế ăn gỗ tự nhiên. Giao hàng miễn phí toàn quốc.',
                'cta_text' => 'Khám Phá · SHOP NOW',
                'link' => '/catalog?category=dining-room',
                'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&auto=format&fit=crop&q=80',
                'open_in_new_tab' => false,
                'sort_order' => 1,
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
                'clicks_count' => 0,
            ],
            [
                'position' => Banner::POSITION_HOME_PROMO_2COL,
                'title' => 'Ghế Thư Giãn Cao Cấp',
                'eyebrow' => 'PREMIUM SEATING · PHÒNG KHÁCH',
                'subtitle' => 'Nâng tầm không gian sống với đệm bọc da thật và khung gỗ tần bì nguyên khối nhập khẩu.',
                'cta_text' => 'Xem Ngay · SEE MORE',
                'link' => '/catalog?category=living-room',
                'image' => 'https://images.unsplash.com/photo-1563861826100-9cb868fdbe1c?w=800&auto=format&fit=crop&q=80',
                'open_in_new_tab' => false,
                'sort_order' => 2,
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
                'clicks_count' => 0,
            ],

            // ── 3. Bộ Sưu Tập 3 Cột (home_collection_3col) ─────────────────────
            [
                'position' => Banner::POSITION_HOME_COLLECTION_3COL,
                'title' => 'Đồ Nội Thất Phòng Khách',
                'eyebrow' => 'CURATED · LIVING ROOM',
                'subtitle' => 'Sofa vải bố cao cấp, bàn trà tròn đôi và kệ tivi tối giản.',
                'cta_text' => 'SEE COLLECTIONS',
                'link' => '/catalog?category=living-room',
                'image' => 'https://images.unsplash.com/photo-1540932239986-30128078f3c5?w=800&auto=format&fit=crop&q=80',
                'open_in_new_tab' => false,
                'sort_order' => 1,
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
                'clicks_count' => 0,
            ],
            [
                'position' => Banner::POSITION_HOME_COLLECTION_3COL,
                'title' => 'Trang Trí & Ánh Sáng',
                'eyebrow' => 'CURATED · LIGHTING',
                'subtitle' => 'Đèn thả bàn ăn và đèn sàn gỗ phong cách Hygge ấm áp.',
                'cta_text' => 'SEE COLLECTIONS',
                'link' => '/catalog?category=lighting',
                'image' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?w=800&auto=format&fit=crop&q=80',
                'open_in_new_tab' => false,
                'sort_order' => 2,
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
                'clicks_count' => 0,
            ],
            [
                'position' => Banner::POSITION_HOME_COLLECTION_3COL,
                'title' => 'Phụ Kiện Nghệ Thuật',
                'eyebrow' => 'CURATED · ACCESSORIES',
                'subtitle' => 'Bình gốm thủ công, khay trang trí và đồng hồ treo tường Bắc Âu.',
                'cta_text' => 'SEE COLLECTIONS',
                'link' => '/catalog?category=accessories',
                'image' => 'https://images.unsplash.com/photo-1534349762230-e0cadf78f5da?w=800&auto=format&fit=crop&q=80',
                'open_in_new_tab' => false,
                'sort_order' => 3,
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
                'clicks_count' => 0,
            ],
        ];

        foreach ($banners as $b) {
            Banner::updateOrCreate(
                ['position' => $b['position'], 'title' => $b['title']],
                $b
            );
        }
    }
}
