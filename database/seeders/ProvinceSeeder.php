<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    /**
     * Seed 34 tỉnh/thành phố Việt Nam theo Nghị quyết 202/2025/QH15.
     *
     * Hiệu lực: 01/07/2025. Giảm từ 63 → 34 đơn vị hành chính cấp tỉnh.
     * Gồm 28 tỉnh + 6 thành phố trực thuộc Trung ương.
     *
     * Mã code: 2 chữ số, theo thứ tự địa lý Bắc → Nam.
     * Các tỉnh sáp nhập ghi chú thành phần cũ trong comment.
     */
    public function run(): void
    {
        $provinces = [
            // ── 6 THÀNH PHỐ TRỰC THUỘC TRUNG ƯƠNG ──────────────────────────
            ['code' => '01', 'name' => 'Hà Nội'],                // Giữ nguyên
            ['code' => '02', 'name' => 'Hải Phòng'],             // + Hải Dương
            ['code' => '03', 'name' => 'Đà Nẵng'],               // + Quảng Nam
            ['code' => '04', 'name' => 'TP. Hồ Chí Minh'],       // Giữ nguyên
            ['code' => '05', 'name' => 'Cần Thơ'],               // + Hậu Giang
            ['code' => '06', 'name' => 'Huế'],                   // Giữ nguyên (TP. trực thuộc TW từ 2025)

            // ── 28 TỈNH ─────────────────────────────────────────────────────
            // Miền Bắc
            ['code' => '10', 'name' => 'Tuyên Quang'],           // + Hà Giang
            ['code' => '11', 'name' => 'Lào Cai'],               // + Yên Bái
            ['code' => '12', 'name' => 'Điện Biên'],             // Giữ nguyên
            ['code' => '13', 'name' => 'Lai Châu'],              // Giữ nguyên
            ['code' => '14', 'name' => 'Sơn La'],                // Giữ nguyên
            ['code' => '15', 'name' => 'Cao Bằng'],              // Giữ nguyên
            ['code' => '16', 'name' => 'Lạng Sơn'],              // Giữ nguyên
            ['code' => '17', 'name' => 'Quảng Ninh'],            // Giữ nguyên
            ['code' => '18', 'name' => 'Thái Nguyên'],           // + Bắc Kạn
            ['code' => '19', 'name' => 'Phú Thọ'],               // + Vĩnh Phúc + Hoà Bình
            ['code' => '20', 'name' => 'Bắc Ninh'],              // + Bắc Giang
            ['code' => '21', 'name' => 'Hưng Yên'],              // + Thái Bình
            ['code' => '22', 'name' => 'Ninh Bình'],             // + Hà Nam + Nam Định
            ['code' => '23', 'name' => 'Thanh Hóa'],             // Giữ nguyên
            ['code' => '24', 'name' => 'Nghệ An'],               // Giữ nguyên

            // Miền Trung
            ['code' => '25', 'name' => 'Hà Tĩnh'],               // Giữ nguyên
            ['code' => '26', 'name' => 'Quảng Bình'],            // + Quảng Trị
            ['code' => '27', 'name' => 'Bình Định'],             // + Phú Yên
            ['code' => '28', 'name' => 'Khánh Hòa'],             // + Ninh Thuận

            // Tây Nguyên
            ['code' => '29', 'name' => 'Kon Tum'],               // + Gia Lai
            ['code' => '30', 'name' => 'Đắk Lắk'],              // + Đắk Nông
            ['code' => '31', 'name' => 'Lâm Đồng'],             // + Bình Thuận

            // Miền Nam - Đông Nam Bộ
            ['code' => '32', 'name' => 'Bình Phước'],            // + Tây Ninh
            ['code' => '33', 'name' => 'Bình Dương'],            // + Đồng Nai + Bà Rịa - Vũng Tàu

            // Miền Nam - Đồng bằng Sông Cửu Long
            ['code' => '34', 'name' => 'Long An'],               // + Tiền Giang
            ['code' => '35', 'name' => 'Bến Tre'],               // + Trà Vinh
            ['code' => '36', 'name' => 'An Giang'],              // + Đồng Tháp
            ['code' => '37', 'name' => 'Vĩnh Long'],             // + Sóc Trăng
            ['code' => '38', 'name' => 'Kiên Giang'],            // + Bạc Liêu + Cà Mau
        ];

        $now = now();
        $data = array_map(fn ($p) => array_merge($p, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $provinces);

        // updateOrInsert — safe to re-run, idempotent
        foreach ($data as $province) {
            DB::table('provinces')->updateOrInsert(
                ['code' => $province['code']],
                $province
            );
        }
    }
}
