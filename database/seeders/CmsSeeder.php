<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CmsSeeder extends Seeder
{
    /**
     * Seed the CMS blog categories, detailed Scandinavian articles with contextual commerce links, and policy pages.
     */
    public function run(): void
    {
        // ── 1. AUTHOR (Admin User) ──────────────────────────────────────────
        $admin = User::firstWhere('email', 'admin@example.com') ?? User::first();
        $authorId = $admin?->id;

        // ── 2. POST CATEGORIES ──────────────────────────────────────────────
        $categoriesData = [
            [
                'name' => 'Kiến Thức Nội Thất',
                'slug' => 'kien-thuc-noi-that',
                'description' => 'Cẩm nang bài trí không gian, nguyên lý thiết kế ánh sáng, phối màu và nghệ thuật kiến tạo tổ ấm phong cách Bắc Âu.',
                'is_active' => true,
                'sort_order' => 1,
                'seo_title' => 'Kiến Thức Nội Thất Bắc Âu — Sober Furniture',
                'seo_description' => 'Khám phá cẩm nang chuyên sâu về bài trí nội thất Scandinavian, nguyên tắc ánh sáng và lựa chọn vật liệu cao cấp.',
            ],
            [
                'name' => 'Phong Cách Sống',
                'slug' => 'phong-cach-song',
                'description' => 'Khám phá triết lý sống Hygge & Lagom, cảm hứng sống chậm, tinh giản và những câu chuyện kiến trúc đương đại.',
                'is_active' => true,
                'sort_order' => 2,
                'seo_title' => 'Phong Cách Sống Scandinavian — Sober Furniture',
                'seo_description' => 'Lan tỏa tinh thần sống tối giản, bình yên và trọn vẹn với phong cách sống đặc trưng vùng Scandinavia.',
            ],
            [
                'name' => 'Hướng Dẫn Bảo Quản',
                'slug' => 'huong-dan-bao-quan',
                'description' => 'Hướng dẫn chi tiết quy trình bảo dưỡng đồ gỗ tự nhiên, kim loại sơn tĩnh điện, đồ da và vải nỉ cao cấp.',
                'is_active' => true,
                'sort_order' => 3,
                'seo_title' => 'Hướng Dẫn Bảo Quản Nội Thất — Sober Furniture',
                'seo_description' => 'Bí quyết vệ sinh, bảo dưỡng bàn ghế gỗ sồi, sofa vải nỉ và đèn trang trí luôn bền đẹp như mới.',
            ],
        ];

        $createdCategories = [];
        foreach ($categoriesData as $cat) {
            $createdCategories[$cat['slug']] = PostCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }

        // ── 3. DETAILED SCANDINAVIAN ARTICLES ───────────────────────────────
        $postsData = [
            [
                'post_category_id' => $createdCategories['kien-thuc-noi-that']->id ?? null,
                'user_id' => $authorId,
                'title' => 'Nghệ thuật bài trí ánh sáng ấm cúng cho không gian phòng khách Scandinavian',
                'slug' => 'nghe-thuat-bai-tri-anh-sang-scandinavian',
                'excerpt' => 'Ánh sáng là linh hồn của phong cách nội thất Bắc Âu. Khám phá cách kết hợp đèn thả trần, đèn bàn và ánh sáng tự nhiên để tạo nên không gian ấm cúng (Hygge) chuẩn mực.',
                'featured_image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=1200&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1513506003901-1e6a229e2d15?w=1600&auto=format&fit=crop&q=80',
                'og_image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=1200&auto=format&fit=crop&q=80',
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(10),
                'is_featured' => true,
                'schema_type' => 'BlogPosting',
                'seo_title' => 'Nghệ Thuật Bài Trí Ánh Sáng Phòng Khách Scandinavian — Sober Furniture',
                'seo_description' => 'Bí quyết phân tầng ánh sáng và bố trí đèn thả trần, đèn bàn cho phòng khách phong cách Bắc Âu ấm cúng chuẩn Hygge.',
                'meta_keywords' => 'ánh sáng scandinavian, đèn thả trần, đèn bàn phong cách bắc âu, nội thất hygge',
                'body' => <<<'HTML'
<p class="lead">Trong văn hóa Bắc Âu, nơi mùa đông kéo dài với những ngày ngắn ngủi, ánh sáng không đơn thuần chỉ là công cụ chiếu sáng mà đã trở thành một biểu tượng tinh thần — hiện thân của niềm ấm áp, sự chở che và linh hồn của phong cách sống Hygge.</p>

<h2>1. Triết Lý Ánh Sáng Trong Không Gian Sống Bắc Âu</h2>
<p>Người Scandinavia hiếm khi sử dụng một bóng đèn chùm công suất lớn duy nhất để rọi sáng toàn bộ căn phòng. Thay vào đó, họ tạo ra các "hòn đảo ánh sáng" (islands of light) bằng cách bố trí nhiều nguồn sáng nhỏ phân tán khắp không gian.</p>

<h3>1.1. Tận Dụng Tối Đa Nguồn Sáng Tự Nhiên</h3>
<p>Để đón nhận ánh sáng mặt trời một cách trọn vẹn nhất, các ô cửa sổ phòng khách thường được giữ thông thoáng tối đa. Hãy ưu tiên sử dụng rèm vải voan hoặc rèm lanh mỏng màu trắng để lọc ánh sáng êm dịu mà không làm tối căn phòng. Ngoài ra, việc đặt gương đối diện nguồn sáng tự nhiên sẽ giúp nhân đôi độ sáng và tạo cảm giác không gian rộng mở hơn.</p>

<h3>1.2. Nguyên Tắc Phân Tầng Ánh Sáng Đa Điểm</h3>
<p>Một hệ thống chiếu sáng chuẩn Scandinavian luôn bao gồm ba tầng ánh sáng cơ bản:</p>
<ul>
    <li><strong>Ánh sáng môi trường (Ambient Light):</strong> Tạo nền sáng tổng thể dịu nhẹ cho toàn bộ căn phòng.</li>
    <li><strong>Ánh sáng tác vụ (Task Light):</strong> Cung cấp ánh sáng tập trung cho các hoạt động cụ thể như đọc sách, làm việc hoặc dùng bữa.</li>
    <li><strong>Ánh sáng điểm nhấn (Accent Light):</strong> Làm nổi bật các chi tiết điêu khắc, tranh treo tường hay các góc decor nghệ thuật.</li>
</ul>

<h2>2. Chọn Đèn Thả Trần Điểm Nhấn Nghệ Thuật</h2>
<p>Đèn thả trần với chao nhôm dập nguyên khối sơn tĩnh điện mờ như dòng <em>Ambit Pendant Lamp</em> luôn là lựa chọn hàng đầu cho khu vực bàn trà hoặc bàn ăn. Thiết kế tối giản cùng đường cong mềm mại giúp ánh sáng lan tỏa đều xuống mặt phẳng mà không gây chói mắt người đối diện.</p>

<h3>2.1. Độ Cao Treo Đèn Chuẩn Xác</h3>
<p>Khoảng cách lý tưởng từ mép dưới chao đèn thả đến mặt bàn là từ <strong>65cm đến 75cm</strong>. Khoảng cách này đảm bảo nguồn sáng hội tụ đẹp mắt trên mặt bàn mà không cản trở tầm nhìn giao tiếp giữa các thành viên trong gia đình.</p>

<h3>2.2. Nhiệt Độ Màu Ánh Sáng Lý Tưởng (2700K - 3000K)</h3>
<p>Nhiệt độ màu từ 2700K đến 3000K (ánh sáng vàng ấm dịu nhẹ) mang lại cảm giác thư thái và giải tỏa căng thẳng sau một ngày làm việc bận rộn. Tránh sử dụng ánh sáng trắng xanh lạnh lẽo (trên 5000K) trong không gian phòng khách vì sẽ làm mất đi nét đặc trưng ấm cúng của phong cách Bắc Âu.</p>

<h2>3. Đèn Bàn & Đèn Chiếu Điểm Tạo Chiều Sâu</h2>
<p>Sự kết hợp giữa chất liệu bê tông thô mộc và thân gỗ sồi tự nhiên trên các mẫu đèn bàn decor giúp tạo nên chiều sâu xúc cảm cho các góc chết trong phòng. Đặt một chiếc đèn bàn cạnh ghế bành đọc sách vừa đáp ứng công năng vừa tạo nên một góc thư giãn đậm chất thơ.</p>

<h2>4. Lời Khuyên Hoàn Thiện Không Gian Hygge</h2>
<p>Hãy bổ sung thêm ánh nến tự nhiên từ sáp ong hoặc sáp đậu nành vào các buổi tối cuối tuần. Ánh lửa bập bùng hòa quyện cùng ánh đèn vàng dịu nhẹ sẽ mang lại cho bạn và gia đình những khoảnh khắc sum vầy trọn vẹn và an yên nhất.</p>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Nhiệt độ màu nào phù hợp nhất cho phòng khách phong cách Scandinavian?',
                        'answer' => 'Nhiệt độ màu lý tưởng là từ 2700K đến 3000K (ánh sáng vàng ấm dịu nhẹ). Tông màu này giúp không gian có cảm giác ấm cúng, thư thái và giảm độ chói mắt.',
                    ],
                    [
                        'question' => 'Nên treo đèn thả trần Ambit Pendant Lamp cách mặt bàn bao nhiêu cm?',
                        'answer' => 'Khoảng cách tiêu chuẩn từ đáy chao đèn thả đến mặt bàn ăn hoặc bàn trà là từ 65cm đến 75cm để tối ưu vùng sáng và không cản trở tầm nhìn đối thoại.',
                    ],
                    [
                        'question' => 'Làm sao để tối ưu ánh sáng tự nhiên trong căn hộ diện tích nhỏ?',
                        'answer' => 'Hãy sử dụng rèm vải voan mỏng màu trắng, bố trí gương soi đối diện cửa sổ để phản chiếu ánh sáng và chọn sơn tường tông trắng mờ hoặc xám tro nhạt.',
                    ],
                ],
                'products' => [
                    ['slug' => 'ambit-pendant-lamp', 'sort_order' => 1],
                    ['slug' => 'cement-wood-lamp', 'sort_order' => 2],
                    ['slug' => 'synnes-dining-chair', 'sort_order' => 3],
                ],
            ],

            [
                'post_category_id' => $createdCategories['huong-dan-bao-quan']->id ?? null,
                'user_id' => $authorId,
                'title' => 'Bí quyết lựa chọn và bảo quản bàn ghế gỗ sồi tự nhiên luôn bền đẹp như mới',
                'slug' => 'bi-quyet-lua-chon-va-bao-quan-ban-ghe-go-soi',
                'excerpt' => 'Gỗ sồi (Oak) là chất liệu được ưa chuộng hàng đầu trong nội thất Bắc Âu. Cẩm nang bảo dưỡng, xử lý độ ẩm và giữ màu vân gỗ sáng đẹp trường tồn theo thời gian.',
                'featured_image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=1200&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1567538096630-e0c55bd6374c?w=1600&auto=format&fit=crop&q=80',
                'og_image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=1200&auto=format&fit=crop&q=80',
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(8),
                'is_featured' => true,
                'schema_type' => 'Article',
                'seo_title' => 'Bí Quyết Lựa Chọn Và Bảo Quản Bàn Ghế Gỗ Sồi — Sober Furniture',
                'seo_description' => 'Hướng dẫn toàn diện về cách chăm sóc, xử lý ẩm mốc và lau dầu dưỡng định kỳ cho bàn ghế gỗ sồi tự nhiên nhập khẩu.',
                'meta_keywords' => 'bảo quản gỗ sồi, bàn ăn gỗ sồi, ghế ăn synnes, vệ sinh đồ gỗ nội thất',
                'body' => <<<'HTML'
<p class="lead">Gỗ sồi tự nhiên (Oak Wood) với các đường vân núi uyển chuyển và sắc gỗ tươi sáng luôn là "linh hồn vật liệu" trong các thiết kế nội thất Scandinavian cao cấp. Tuy nhiên, để đồ gỗ giữ được vẻ đẹp nguyên bản qua hàng chục năm sử dụng đòi hỏi sự thấu hiểu và chăm sóc đúng cách.</p>

<h2>1. Đặc Tính Vượt Trội Của Gỗ Sồi Tự Nhiên</h2>
<p>Gỗ sồi trắng (White Oak) nhập khẩu Bắc Mỹ sở hữu độ cứng chắc cao, thớ gỗ mịn và cấu trúc dạng chai khép kín giúp hạn chế tối đa sự xâm nhập của nước và hơi ẩm vào tâm gỗ.</p>

<h3>1.1. Cấu Trúc Vân Gỗ Và Khả Năng Chịu Lực</h3>
<p>Nhờ liên kết sợi gỗ bền chặt, các mẫu ghế như <em>Synnes Dining Chair</em> hay bàn làm việc <em>Copenhague Desk</em> có thể chịu được tải trọng lớn mà vẫn giữ được đường nét thanh mảnh, thanh thoát.</p>

<h3>1.2. Kháng Mối Mọt Tự Nhiên</h3>
<p>Gỗ sồi chứa hàm lượng tannin tự nhiên cao — một chất kháng côn trùng và nấm mốc hữu cơ cực kỳ hiệu quả, giúp đồ nội thất thích nghi tốt với điều kiện khí hậu nhiệt đới gió mùa tại Việt Nam.</p>

<h2>2. Quy Trình Vệ Sinh Bàn Ghế Gỗ Hàng Ngày</h2>
<p>Việc vệ sinh định kỳ giúp loại bỏ bụi bẩn trước khi chúng bám sâu vào các kẽ vân gỗ.</p>

<h3>2.1. Sử Dụng Khăn Mềm Vải Microfiber</h3>
<p>Chỉ nên sử dụng khăn vải sợi microfiber ẩm nhẹ (đã vắt ráo nước hoàn toàn) để lau dọc theo chiều vân gỗ. Tuyệt đối không dùng các loại bàn chải cứng hay giẻ lau thô ráp dễ gây trầy xước lớp phủ bảo vệ.</p>

<h3>2.2. Tránh Xa Hóa Chất Tẩy Rửa Gốc Axit Mạnh</h3>
<p>Không dùng nước lau kính, cồn công nghiệp hoặc dung dịch tẩy rửa đa năng chứa chất tẩy mạnh để vệ sinh gỗ. Nếu cần làm sạch vết dầu mỡ, hãy pha loãng một vài giọt nước rửa chén dịu nhẹ với nước ấm.</p>

<h2>3. Dưỡng Dầu Bảo Vệ Bề Mặt Định Kỳ (6 Tháng/Lần)</h2>
<p>Sau mỗi 6 tháng, bạn nên thoa một lớp dầu dưỡng chuyên dụng (như dầu lau gốc thực vật hoặc sáp ong tự nhiên). Dầu sẽ thẩm thấu sâu vào tế bào gỗ, nuôi dưỡng độ ẩm từ bên trong và phục hồi độ bóng mờ tự nhiên của bề mặt.</p>

<h2>4. Xử Lý Các Sự Cố Trầy Xước & Vết Ố Nước Thường Gặp</h2>
<p>Khi bề mặt xuất hiện vết xước nông do cọ xát, hãy dùng giấy nhám siêu mịn (độ nhám P400 - P600) chà thật nhẹ tay theo chiều vân gỗ, sau đó chấm một lượng nhỏ dầu dưỡng gỗ lên vùng xử lý và dùng khăn sạch xoa đều.</p>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Bao lâu nên lau dầu dưỡng bóng cho bàn ghế gỗ sồi một lần?',
                        'answer' => 'Bạn nên thoa dầu lau gỗ chuyên dụng (như dầu lau gốc thực vật Rubio Monocoat hoặc sáp sồi tự nhiên) định kỳ 6 tháng một lần để duy trì độ ẩm và kháng nước.',
                    ],
                    [
                        'question' => 'Xử lý thế nào khi bề mặt gỗ sồi bị dính vết nước trà hoặc cà phê?',
                        'answer' => 'Hãy dùng khăn microfiber ẩm lau ngay lập tức. Nếu có vết ố nhẹ, dùng giấy nhám mịn P400 chà nhẹ theo chiều vân gỗ rồi thoa một lớp dầu bảo dưỡng mỏng.',
                    ],
                    [
                        'question' => 'Có nên đặt bàn ghế gỗ sồi trực tiếp dưới ánh nắng mặt trời gắt không?',
                        'answer' => 'Tuyệt đối không nên. Ánh nắng trực tiếp và nhiệt độ cao liên tục có thể làm gỗ bị co ngót, nứt chân chim hoặc biến đổi sắc tố vân gỗ tự nhiên.',
                    ],
                ],
                'products' => [
                    ['slug' => 'synnes-dining-chair', 'sort_order' => 1],
                    ['slug' => 'copenhague-desk', 'sort_order' => 2],
                    ['slug' => 'around-coffee-table', 'sort_order' => 3],
                ],
            ],

            [
                'post_category_id' => $createdCategories['phong-cach-song']->id ?? null,
                'user_id' => $authorId,
                'title' => 'Xu hướng thiết kế nội thất tối giản 2026: Tinh gọn không gian, nâng tầm sống chậm',
                'slug' => 'xu-huong-thiet-ke-noi-that-toi-gian-2026',
                'excerpt' => 'Xu hướng nội thất 2026 hướng đến sự cân bằng hoàn hảo giữa thẩm mỹ tối giản và công năng sống thông minh. Khám phá cách bài trí không gian sống thanh lọc tâm trí.',
                'featured_image' => 'https://images.unsplash.com/photo-1563861826100-9cb868fdbe1c?w=1200&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=1600&auto=format&fit=crop&q=80',
                'og_image' => 'https://images.unsplash.com/photo-1563861826100-9cb868fdbe1c?w=1200&auto=format&fit=crop&q=80',
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(6),
                'is_featured' => true,
                'schema_type' => 'BlogPosting',
                'seo_title' => 'Xu Hướng Nội Thất Tối Giản 2026 — Sober Furniture',
                'seo_description' => 'Định hình phong cách sống tinh tế với xu hướng nội thất tối giản hiện đại năm 2026: Đề cao giá trị công năng và xúc cảm bình yên.',
                'meta_keywords' => 'xu hướng nội thất 2026, thiết kế tối giản, phong cách sống lagom, đồng hồ freakish',
                'body' => <<<'HTML'
<p class="lead">Bước sang năm 2026, chủ nghĩa tối giản (Minimalism) không còn là sự giản lược lạnh lẽo đến mức kham khổ, mà đã tiến hóa thành phong cách "Warm Minimalism" — sự tinh gọn đầy tính nhân văn, tập trung vào trải nghiệm cảm xúc và sức khỏe tinh thần của con người.</p>

<h2>1. Định Nghĩa Lại Chủ Nghĩa Tối Giản Trong Năm 2026</h2>
<p>Triết lý sống Lagom (biết đủ và cân bằng) của người Thụy Điển đang trở thành kim chỉ nam cho các kiến trúc sư hiện đại. Mọi vật dụng đặt để trong căn phòng đều phải mang một ý nghĩa rõ ràng: phục vụ nhu cầu sử dụng thực tế hoặc khơi gợi niềm vui thẩm mỹ.</p>

<h3>1.1. Từ Tối Giản Hình Thức Sang Tối Giản Cảm Xúc</h3>
<p>Thay vì những mảng tường trắng toát không tì vết, không gian năm 2026 chào đón các bề mặt vật liệu có kết cấu xúc giác rõ rệt: tường vữa thô, sàn gỗ sồi mộc mạc và những bộ rèm vải thô dệt thủ công.</p>

<h3>1.2. Tôn Vinh Các Vật Liệu Tự Nhiên Thô Mộc</h3>
<p>Sự kết hợp giữa gốm sứ thủ công, thép sơn mờ tĩnh điện và gỗ bạch dương uốn dẻo tạo nên một không gian sống cân bằng, xóa nhòa ranh giới giữa thiên nhiên và kiến trúc đô thị.</p>

<h2>2. Nghệ Thuật Lựa Chọn Phụ Kiện Trang Trí Điêu Khắc</h2>
<p>Một không gian tối giản không có nghĩa là không có đồ trang trí. Điều quan trọng là số lượng ít nhưng chất lượng vượt trội.</p>

<h3>2.1. Đồng Hồ Treo Tường Tối Giản Như Tác Phẩm Nghệ Thuật</h3>
<p>Đồng hồ <em>Freakish Clock</em> với thiết kế đĩa xoay không kim là minh chứng tiêu biểu cho phụ kiện nội thất điêu khắc. Nó không chỉ là công cụ đo đếm thời gian mà còn là điểm nhấn thị giác thu hút mọi ánh nhìn trên mảng tường phòng khách.</p>

<h3>2.2. Đồ Dùng Bếp Gốm Sứ & Silicon Tinh Tế</h3>
<p>Bộ cối xay muối tiêu <em>Bottle Grinders Set</em> với hình dáng bình nước tối giản vừa giúp căn bếp luôn ngăn nắp, vừa thể hiện gu thẩm mỹ tinh tế của gia chủ trong từng thói quen nấu nướng hàng ngày.</p>

<h2>3. Ghế Đẩu Xếp Chồng: Giải Pháp Đa Năng Cho Không Gian Nhỏ</h2>
<p>Mẫu ghế đẩu <em>Arte 60 Stool</em> với cấu trúc chân chữ L kinh điển có thể đóng vai trò làm ghế ngồi tiếp khách, bàn trà phụ cạnh sofa hoặc đôn kê chậu cây. Khi không dùng đến, chúng có thể xếp chồng gọn gàng thành một tác phẩm điêu khắc xoắn ốc tuyệt đẹp.</p>

<h2>4. Kết Luận: Sống Đủ Đầy Trong Không Gian Tinh Giản</h2>
<p>Tối giản không gian chính là cách bạn giải phóng tâm trí khỏi những xao nhãng của cuộc sống hiện đại, để trở về nhà là trở về với sự bình yên đích thực.</p>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Tối giản (Minimalism) có đồng nghĩa với việc để căn phòng trống trải và lạnh lẽo không?',
                        'answer' => 'Không. Phong cách Warm Minimalism hiện đại tập trung vào việc loại bỏ đồ vật dư thừa nhưng vẫn giữ được sự ấm áp nhờ vật liệu gỗ, vải dệt thô và ánh sáng vàng dịu.',
                    ],
                    [
                        'question' => 'Làm thế nào để bắt đầu tinh giản không gian sống gia đình?',
                        'answer' => 'Hãy bắt đầu bằng việc dọn dẹp các bề mặt phẳng (mặt bàn, kệ tủ), phân loại đồ dùng theo nguyên tắc công năng và đầu tư vào một số món nội thất chất lượng cao có tính thẩm mỹ vượt thời gian.',
                    ],
                ],
                'products' => [
                    ['slug' => 'freakish-clock', 'sort_order' => 1],
                    ['slug' => 'bottle-grinders-set', 'sort_order' => 2],
                    ['slug' => 'arte-60-stool', 'sort_order' => 3],
                ],
            ],

            [
                'post_category_id' => $createdCategories['kien-thuc-noi-that']->id ?? null,
                'user_id' => $authorId,
                'title' => 'Cách phối hợp màu sắc trung tính và chất liệu thô mộc trong căn hộ hiện đại',
                'slug' => 'cach-phoi-hop-mau-sac-trung-tinh-va-chat-lieu-tho-moc',
                'excerpt' => 'Quy tắc vàng 60-30-10 trong phối màu nội thất phong cách Scandinavia: Sự hòa quyện giữa tông trắng xám, gỗ mộc và kim loại sơn tĩnh điện hiện đại.',
                'featured_image' => 'https://images.unsplash.com/photo-1540932239986-30128078f3c5?w=1200&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=1600&auto=format&fit=crop&q=80',
                'og_image' => 'https://images.unsplash.com/photo-1540932239986-30128078f3c5?w=1200&auto=format&fit=crop&q=80',
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(4),
                'is_featured' => false,
                'schema_type' => 'BlogPosting',
                'seo_title' => 'Cách Phối Hợp Màu Sắc Trung Tính & Chất Liệu Thô Mộc — Sober Furniture',
                'seo_description' => 'Áp dụng quy tắc phối màu chuẩn Bắc Âu để tạo nên sự cân bằng hoàn hảo giữa nét hiện đại và vẻ đẹp mộc mạc.',
                'meta_keywords' => 'phối màu nội thất, màu trung tính scandinavian, bê tông và gỗ, quy tắc 60-30-10',
                'body' => <<<'HTML'
<p class="lead">Sử dụng bảng màu trung tính (Neutral Palette) kết hợp cùng các chất liệu mộc mạc như gỗ tự nhiên, bê tông và kim loại sơn mờ là công thức kinh điển để tạo nên một không gian sống thanh lịch và không bao giờ lỗi mốt.</p>

<h2>1. Bảng Màu Trung Tính — Nền Tảng Của Không Gian Bắc Âu</h2>
<p>Các gam màu trung tính không chỉ giúp phản xạ ánh sáng tốt hơn mà còn tạo ra một phông nền tĩnh lặng, giúp tôn vinh hình khối của từng món đồ nội thất.</p>

<h3>1.1. Sắc Trắng Mờ, Xám Tro Và Màu Cát</h3>
<p>Thay vì màu trắng tinh (pure white) dễ gây cảm giác chói gắt của bệnh viện, hãy ưu tiên các tông trắng kem (off-white), xám tro nhạt (ash gray) hoặc màu be cát (sand beige). Những gam màu này có độ dịu mắt cao và tạo cảm giác thư giãn tuyệt đối.</p>

<h3>1.2. Quy Tắc Tỉ Lệ Vàng 60-30-10 Trong Bố Trí Màu Sắc</h3>
<p>Để căn phòng không bị đơn điệu hay rối mắt, hãy tuân thủ tỉ lệ màu sắc:</p>
<ul>
    <li><strong>60% Màu chủ đạo:</strong> Dành cho tường, trần và sàn nhà (thường là tông trắng ngà hoặc xám sáng).</li>
    <li><strong>30% Màu bổ trợ:</strong> Dành cho các khối nội thất chính như sofa vải nỉ, bàn ăn gỗ sồi và rèm cửa.</li>
    <li><strong>10% Màu điểm nhấn:</strong> Dành cho các chi tiết kim loại đen, đèn chiếu sáng, gối tựa hoặc phụ kiện trang trí nhỏ.</li>
</ul>

<h2>2. Sự Kết Hợp Tương Phản Giữa Bê Tông, Kim Loại Và Gỗ Sồi</h2>
<p>Sự quyến rũ của phong cách nội thất Scandinavian hiện đại nằm ở nghệ thuật tương phản vật liệu (material contrast).</p>

<h3>2.1. Đèn Bàn Chân Bê Tông — Điểm Nhấn Kiến Trúc Thô Mộc</h3>
<p>Chiếc đèn <em>Cement Wood Lamp</em> với chân đế bê tông đúc khuôn kết hợp khớp nối gỗ sồi mộc mạc chính là ví dụ hoàn hảo cho sự giao thoa giữa nét thô ráp công nghiệp và sự ấm áp của tự nhiên.</p>

<h3>2.2. Kim Loại Sơn Tĩnh Điện Đen Tạo Đường Nét Sắc Sảo</h3>
<p>Khung kim loại thanh mảnh trên đèn thả <em>Ambit Pendant Lamp</em> tạo ra những đường viền sắc nét, định hình không gian rõ ràng và mang đến nét chấm phá đương đại cho căn hộ.</p>

<h2>3. Tận Dụng Vải Dệt Tự Nhiên Để Làm Mềm Không Gian</h2>
<p>Để cân bằng lại tính cứng của bê tông và kim loại, hãy đưa vào các chất liệu vải tự nhiên như linen (vải lanh), len thô dệt tay và cotton hữu cơ. Những lớp thảm trải sàn dệt sợi tự nhiên sẽ mang lại cảm giác êm ái dưới đôi chân trần.</p>

<h2>4. Tổng Kết Phối Cảnh Hoàn Chỉnh</h2>
<p>Khi các yếu tố màu sắc, ánh sáng và chất liệu được kết hợp hài hòa theo tỉ lệ chuẩn mực, tổ ấm của bạn sẽ trở thành một chốn về thư thái, ngập tràn cảm hứng sống mỗi ngày.</p>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Tỷ lệ phối màu 60-30-10 áp dụng như thế nào trong phòng khách?',
                        'answer' => '60% là màu chủ đạo (tường, trần, sàn với màu trắng hoặc be nhạt), 30% là màu bổ trợ (sofa nỉ xám, bàn ghế gỗ sồi), 10% là điểm nhấn (đèn kim loại đen, đồng hồ vàng mù tạt).',
                    ],
                    [
                        'question' => 'Chất liệu bê tông đúc có bị thô cứng khi đặt trong phòng ngủ không?',
                        'answer' => 'Khi kết hợp với chao đèn vải lanh thô và chi tiết gỗ sồi ấm áp, bê tông tạo nên vẻ đẹp điêu khắc hiện đại, độc đáo và rất yên tĩnh cho góc phòng ngủ.',
                    ],
                ],
                'products' => [
                    ['slug' => 'cement-wood-lamp', 'sort_order' => 1],
                    ['slug' => 'ambit-pendant-lamp', 'sort_order' => 2],
                    ['slug' => 'bottle-grinders-set', 'sort_order' => 3],
                ],
            ],

            [
                'post_category_id' => $createdCategories['phong-cach-song']->id ?? null,
                'user_id' => $authorId,
                'title' => 'Cẩm nang chọn sofa băng vải bố cao cấp: Kích thước, chất liệu đệm và độ bền',
                'slug' => 'cam-nang-chon-sofa-bang-vai-bo-cao-cap',
                'excerpt' => 'Sofa là tâm điểm của phòng khách gia đình. Hướng dẫn chọn kích thước sofa băng chuẩn tỉ lệ phòng, độ đàn hồi của mút D40 và ưu điểm của chất liệu vải bố dệt cao cấp.',
                'featured_image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?w=1600&auto=format&fit=crop&q=80',
                'og_image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&auto=format&fit=crop&q=80',
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(2),
                'is_featured' => false,
                'schema_type' => 'Article',
                'seo_title' => 'Cẩm Nang Chọn Sofa Băng Vải Bố Cao Cấp — Sober Furniture',
                'seo_description' => 'Kinh nghiệm lựa chọn kích thước, kiểm tra khung gỗ và độ bền đệm mút sofa băng phong cách Scandinavian.',
                'meta_keywords' => 'sofa băng vải bố, sofa phòng khách, sofa outline nordic, kích thước sofa',
                'body' => <<<'HTML'
<p class="lead">Là món đồ nội thất chiếm diện tích lớn nhất và được sử dụng với tần suất cao nhất trong phòng khách, một chiếc sofa băng chất lượng cao không chỉ nâng tầm đẳng cấp không gian mà còn là nơi gắn kết những phút giây sum họp quý giá của cả gia đình.</p>

<h2>1. Tầm Quan Trọng Của Chiếc Sofa Trong Phòng Khách</h2>
<p>Chiếc sofa đóng vai trò như chiếc mỏ neo định hình toàn bộ phong cách kiến trúc của căn nhà. Lựa chọn đúng mẫu sofa sẽ giúp tối ưu hóa luồng giao thông di chuyển và tạo cảm giác thông thoáng cho phòng khách.</p>

<h3>1.1. Trung Tâm Kết Nối Sinh Hoạt Của Cả Gia Đình</h3>
<p>Từ những buổi tối quây quần xem phim, tiếp đón bạn bè thân thiết đến những giờ phút ngả lưng đọc sách thư giãn cuối tuần, chiếc sofa luôn là người bạn đồng hành thân thuộc nhất trong không gian sống.</p>

<h3>1.2. Thước Đo Tỉ Lệ Kiến Trúc Toàn Bộ Căn Phòng</h3>
<p>Một chiếc sofa quá khổ sẽ làm phòng khách trở nên chật chội ngột ngạt, ngược lại một chiếc sofa quá nhỏ sẽ khiến không gian có cảm giác lạc lõng, thiếu cân đối.</p>

<h2>2. Tiêu Chí Chọn Kích Thước Sofa Chuẩn Không Gian</h2>
<p>Trước khi quyết định mua sắm, bạn cần xác định rõ diện tích phòng và nhu cầu sử dụng thực tế của gia đình.</p>

<h3>2.1. Đo Đạc Lối Đi Và Khoảng Cách Đến Bàn Trà</h3>
<p>Khoảng cách tiêu chuẩn từ mép sofa đến bàn trà nên dao động từ <strong>40cm đến 45cm</strong> để việc đứng lên ngồi xuống được thoải mái. Lối đi xung quanh sofa cần tối thiểu <strong>80cm</strong> để việc di chuyển không bị vướng víu.</p>

<h3>2.2. Lựa Chọn Sofa 2 Chỗ Hay 3 Chỗ Cho Căn Hộ Chung Cư</h3>
<p>Với các căn hộ chung cư diện tích từ 60m2 - 90m2, mẫu sofa băng 3 chỗ như <em>Outline Sofa</em> với chiều dài 220cm cùng phần tựa tay mỏng thanh thoát là lựa chọn tối ưu, vừa đủ chỗ ngồi cho cả gia đình vừa tiết kiệm diện tích.</p>

<h2>3. Kết Cấu Khung Gỗ Và Độ Đàn Hồi Của Đệm Mút D40</h2>
<p>Độ bền của một chiếc sofa phụ thuộc 80% vào những cấu phần bên trong mà mắt thường không nhìn thấy được.</p>

<h3>3.1. Khung Gỗ Thông Tự Nhiên Xử Lý Chống Cong Vênh</h3>
<p>Khung sườn chịu lực phải được làm từ gỗ tự nhiên đã qua tẩm sấy đạt chuẩn độ ẩm 10-12%, liên kết mộng gỗ chắc chắn để không phát ra tiếng cọt kẹt sau thời gian dài sử dụng.</p>

<h3>3.2. Ưu Điểm Đệm Mút D40 Chống Xẹp Lún</h3>
<p>Đệm mút D40 cao cấp sở hữu mật độ mút dày dặn (40kg/m3), độ đàn hồi tối ưu giúp nâng đỡ cột sống tự nhiên và giữ form dáng vuông vắn, không bị biến dạng hay xẹp lún.</p>

<h2>4. Ưu Điểm Của Vải Bố Dệt Thô Thoáng Khí</h2>
<p>Chất liệu vải bố (canvas/polyester blend) cao cấp có ưu điểm vượt trội về độ bền kéo, chống bám bụi và đặc biệt là độ thoáng khí cao, không gây cảm giác nóng bí lưng trong những ngày hè oi bức.</p>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Mút D40 có ưu điểm gì so với các loại mút thông thường?',
                        'answer' => 'Mút D40 có mật độ bọt cao (40kg/m3), độ đàn hồi tối ưu, chống xẹp lún sau nhiều năm sử dụng và tạo cảm giác nâng đỡ cột sống êm ái khi ngồi lâu.',
                    ],
                    [
                        'question' => 'Cách vệ sinh sofa vải bố định kỳ tại nhà?',
                        'answer' => 'Hút bụi bề mặt hàng tuần bằng đầu bàn chải mềm. Khi dính vết bẩn cục bộ, dùng bọt xà phòng nhẹ chấm nhẹ và thấm khô bằng khăn giấy, không chà xát mạnh.',
                    ],
                ],
                'products' => [
                    ['slug' => 'outline-sofa-nordic', 'sort_order' => 1],
                    ['slug' => 'around-coffee-table', 'sort_order' => 2],
                    ['slug' => 'synnes-dining-chair', 'sort_order' => 3],
                ],
            ],
        ];

        foreach ($postsData as $pData) {
            $productAttachments = $pData['products'] ?? [];
            unset($pData['products']);

            $post = Post::updateOrCreate(
                ['slug' => $pData['slug']],
                $pData
            );

            if (! empty($productAttachments)) {
                $syncData = [];
                foreach ($productAttachments as $item) {
                    $prod = Product::firstWhere('slug', $item['slug']);
                    if ($prod) {
                        $syncData[$prod->id] = ['sort_order' => $item['sort_order']];
                    }
                }
                $post->products()->sync($syncData);
            }
        }

        // ── 4. POLICY PAGES ─────────────────────────────────────────────────
        $pagesData = [
            [
                'title' => 'Chính Sách Bảo Mật',
                'slug' => 'chinh-sach-bao-mat',
                'template' => 'policy',
                'is_published' => true,
                'published_at' => Carbon::now()->subMonths(2),
                'excerpt' => 'Cam kết của Sober Furniture về bảo vệ thông tin cá nhân và an toàn dữ liệu khách hàng theo tiêu chuẩn bảo mật cao nhất.',
                'seo_title' => 'Chính Sách Bảo Mật Thông Tin — Sober Furniture',
                'seo_description' => 'Tìm hiểu cách Sober Furniture thu thập, sử dụng và bảo mật dữ liệu cá nhân của quý khách hàng khi mua sắm trực tuyến.',
                'body' => <<<'HTML'
<p class="lead">Sober Furniture (MYSHOP) cam kết tôn trọng và bảo mật tuyệt đối các thông tin mang tính riêng tư của quý khách hàng. Bản Chính Sách Bảo Mật này giải thích cách thức chúng tôi thu thập, sử dụng và bảo vệ dữ liệu cá nhân của quý khách.</p>

<h2>1. Mục Đích Thu Thập Thông Tin Cá Nhân</h2>
<p>Chúng tôi thu thập thông tin khách hàng nhằm các mục đích chính đáng sau:</p>
<ul>
    <li>Xử lý và hoàn tất các đơn đặt hàng sản phẩm nội thất trên website.</li>
    <li>Giao hàng tận nhà và cung cấp dịch vụ lắp đặt chuyên nghiệp.</li>
    <li>Cập nhật tình trạng đơn hàng, gửi thông báo vận chuyển và hóa đơn điện tử.</li>
    <li>Hỗ trợ chăm sóc khách hàng, giải quyết khiếu nại và bảo hành sản phẩm.</li>
    <li>Cải thiện trải nghiệm duyệt web và nâng cao chất lượng dịch vụ khách hàng.</li>
</ul>

<h2>2. Phạm Vi Sử Dụng & Chia Sẻ Dữ Liệu</h2>
<p>Thông tin cá nhân của quý khách chỉ được sử dụng nội bộ tại Sober Furniture. Chúng tôi cam kết <strong>không bán, cho thuê hoặc chia sẻ dữ liệu</strong> cho bất kỳ bên thứ ba nào vì mục đích thương mại.</p>
<p>Thông tin chỉ được cung cấp cho các đối tác vận chuyển và cổng thanh toán được ủy quyền nhằm mục đích hoàn tất giao dịch và giao hàng đến địa chỉ yêu cầu.</p>

<h2>3. Cam Kết Bảo Mật Thanh Toán Trực Tuyến</h2>
<p>Mọi giao dịch thanh toán trực tuyến qua thẻ ngân hàng hoặc ví điện tử đều được mã hóa bằng giao thức SSL/TLS 256-bit chuẩn quốc tế. Sober Furniture không lưu trữ bất kỳ thông tin số thẻ tín dụng hay mã bảo mật CVV nào của quý khách trên hệ thống máy chủ của chúng tôi.</p>

<h2>4. Quyền Của Khách Hàng Đối Với Dữ Liệu Cá Nhân</h2>
<p>Quý khách có quyền truy cập, kiểm tra, cập nhật hoặc yêu cầu hủy bỏ thông tin cá nhân của mình bất kỳ lúc nào bằng cách đăng nhập vào tài khoản cá nhân hoặc liên hệ trực tiếp với bộ phận chăm sóc khách hàng của chúng tôi.</p>

<h2>5. Thông Tin Liên Hệ Bộ Phận Bảo Vệ Dữ Liệu</h2>
<p>Nếu quý khách có bất kỳ câu hỏi hoặc thắc mắc nào liên quan đến chính sách bảo mật, xin vui lòng liên hệ:</p>
<ul>
    <li><strong>Email:</strong> privacy@soberfurniture.vn</li>
    <li><strong>Hotline:</strong> 1900 6868 (8:30 - 18:00 từ Thứ Hai đến Thứ Bảy)</li>
    <li><strong>Địa chỉ:</strong> 123 Đường Pasteur, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh</li>
</ul>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Sober Furniture có chia sẻ thông tin khách hàng cho bên thứ ba không?',
                        'answer' => 'Chúng tôi cam kết tuyệt đối không bán, trao đổi hoặc chia sẻ thông tin cá nhân của quý khách cho bên thứ ba vì mục đích thương mại, ngoại trừ các đơn vị vận chuyển đối tác để thực hiện giao hàng.',
                    ],
                    [
                        'question' => 'Làm cách nào để yêu cầu chỉnh sửa hoặc xóa dữ liệu cá nhân?',
                        'answer' => 'Quý khách có thể gửi email yêu cầu tới privacy@soberfurniture.vn hoặc liên hệ hotline 1900 6868 để được hỗ trợ cập nhật hoặc xóa dữ liệu trong vòng 24 giờ làm việc.',
                    ],
                ],
            ],

            [
                'title' => 'Điều Khoản Dịch Vụ',
                'slug' => 'dieu-khoan-dich-vu',
                'template' => 'policy',
                'is_published' => true,
                'published_at' => Carbon::now()->subMonths(2),
                'excerpt' => 'Các điều khoản và quy định điều chỉnh việc sử dụng website và giao dịch mua sắm sản phẩm nội thất tại Sober Furniture.',
                'seo_title' => 'Điều Khoản Dịch Vụ & Sử Dụng Website — Sober Furniture',
                'seo_description' => 'Quy định và điều khoản ràng buộc pháp lý giữa khách hàng và Sober Furniture trong quá trình duyệt web và đặt hàng.',
                'body' => <<<'HTML'
<p class="lead">Chào mừng quý khách đến với website thương mại điện tử Sober Furniture. Bằng việc truy cập, duyệt xem hoặc đặt mua sản phẩm trên website, quý khách đồng ý tuân thủ và chịu sự ràng buộc của các Điều Khoản Dịch Vụ dưới đây.</p>

<h2>1. Chấp Thuận Các Điều Khoản Sử Dụng</h2>
<p>Sober Furniture có quyền điều chỉnh, bổ sung hoặc thay đổi nội dung của Điều Khoản Dịch Vụ bất kỳ lúc nào để phù hợp với quy định pháp luật và hoạt động kinh doanh. Những thay đổi sẽ có hiệu lực ngay khi được đăng tải công khai trên website.</p>

<h2>2. Tài Khoản & Bảo Mật Mật Khẩu</h2>
<p>Khi tạo tài khoản trên website, quý khách có trách nhiệm bảo mật mật khẩu và chịu trách nhiệm đối với toàn bộ các hoạt động diễn ra dưới tài khoản của mình. Vui lòng thông báo ngay cho chúng tôi nếu phát hiện bất kỳ hành vi truy cập trái phép nào.</p>

<h2>3. Giá Cả Và Quy Trình Xác Nhận Đơn Hàng</h2>
<p>Tất cả giá bán sản phẩm niêm yết trên website đã bao gồm thuế Giá Trị Gia Tăng (VAT) theo quy định hiện hành của pháp luật Việt Nam. Giá bán chưa bao gồm phí vận chuyển cồng kềnh ngoại tỉnh (nếu có).</p>
<p>Đơn hàng được coi là xác nhận thành công sau khi hệ thống gửi email tự động xác nhận đơn hàng kèm mã đơn hàng hợp lệ.</p>

<h2>4. Quyền Sở Hữu Trí Tuệ Về Hình Ảnh & Nội Dung</h2>
<p>Toàn bộ hình ảnh sản phẩm, bài viết blog, video và thiết kế giao diện trên website đều thuộc quyền sở hữu trí tuệ độc quyền của Sober Furniture. Nghiêm cấm mọi hành vi sao chép, trích dẫn hoặc sử dụng lại vì mục đích thương mại khi chưa có văn bản chấp thuận.</p>

<h2>5. Giới Hạn Trách Nhiệm & Xử Lý Tranh Chấp</h2>
<p>Mọi tranh chấp phát sinh giữa khách hàng và Sober Furniture trước hết sẽ được ưu tiên giải quyết thông qua thương lượng và hòa giải trên tinh thần tôn trọng quyền lợi của người tiêu dùng.</p>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Đơn hàng được coi là xác nhận thành công khi nào?',
                        'answer' => 'Đơn hàng được xác nhận khi quý khách nhận được email thông báo có mã vận đơn cùng xác nhận từ tổng đài viên chăm sóc khách hàng của Sober Furniture.',
                    ],
                    [
                        'question' => 'Tôi có thể hủy đơn hàng sau khi đã thanh toán không?',
                        'answer' => 'Quý khách có thể hủy đơn hàng miễn phí trong vòng 2 giờ kể từ khi đặt nếu đơn hàng chưa được chuyển giao cho đối tác vận chuyển.',
                    ],
                ],
            ],

            [
                'title' => 'Chính Sách Vận Chuyển & Đổi Trả',
                'slug' => 'chinh-sach-van-chuyen-doi-tra',
                'template' => 'policy',
                'is_published' => true,
                'published_at' => Carbon::now()->subMonths(2),
                'excerpt' => 'Chính sách giao hàng tận nơi toàn quốc, hỗ trợ lắp đặt miễn phí tại nội thành và quy trình đổi trả hàng trong 30 ngày.',
                'seo_title' => 'Chính Sách Vận Chuyển & Đổi Trả Hàng — Sober Furniture',
                'seo_description' => 'Chi tiết thời gian giao hàng, phí vận chuyển toàn quốc và điều kiện đổi trả sản phẩm lỗi trong vòng 30 ngày tại Sober Furniture.',
                'body' => <<<'HTML'
<p class="lead">Nhằm mang lại trải nghiệm mua sắm nội thất an tâm và thuận tiện nhất, Sober Furniture áp dụng chính sách giao hàng tận phòng, lắp đặt chuyên nghiệp và đổi trả linh hoạt trong vòng 30 ngày.</p>

<h2>1. Phạm Vi Giao Hàng & Thời Gian Vận Chuyển</h2>
<p>Chúng tôi cung cấp dịch vụ giao hàng tận nơi trên phạm vi toàn quốc:</p>
<ul>
    <li><strong>Nội thành TP.HCM & Hà Nội:</strong> Giao hàng trong vòng 24 - 48 giờ làm việc. Có hỗ trợ giao hỏa tốc 4 giờ cho phụ kiện decor.</li>
    <li><strong>Các tỉnh thành khác:</strong> Giao hàng trong vòng 3 - 5 ngày làm việc qua các đối tác vận chuyển chuyên dụng đồ nội thất.</li>
</ul>

<h2>2. Biểu Phí Vận Chuyển & Lắp Đặt Tận Nhà</h2>
<p>Miễn phí vận chuyển và lắp đặt tiêu chuẩn cho tất cả đơn hàng nội thành có giá trị từ <strong>5.000.000 VNĐ</strong> trở lên. Đối với các đơn hàng dưới ngưỡng hoặc giao tỉnh xa, phí vận chuyển sẽ được tính toán tự động dựa trên trọng lượng và khoảng cách địa lý tại trang Thanh Toán.</p>

<h2>3. Điều Kiện & Quy Trình Đổi Trả Sản Phẩm Trong 30 Ngày</h2>
<p>Quý khách được quyền đổi sang sản phẩm khác hoặc hoàn tiền trong vòng <strong>30 ngày</strong> kể từ ngày nhận hàng nếu sản phẩm đáp ứng các tiêu chí:</p>
<ul>
    <li>Sản phẩm còn nguyên trạng, chưa qua sử dụng và không bị trầy xước do tác động ngoại lực của người dùng.</li>
    <li>Còn đầy đủ bao bì đóng gói, túi bảo vệ, phụ kiện lắp ráp và tem nhãn của nhà sản xuất.</li>
    <li>Có hóa đơn mua hàng điện tử hoặc số điện thoại đặt hàng trùng khớp trên hệ thống.</li>
</ul>

<h2>4. Các Trường Hợp Không Áp Dụng Đổi Trả</h2>
<p>Chính sách đổi trả không áp dụng đối với:</p>
<ul>
    <li>Sản phẩm đặt may đo theo kích thước hoặc màu sắc riêng của khách hàng.</li>
    <li>Sản phẩm thuộc danh mục xả kho thanh lý giảm giá trên 50%.</li>
    <li>Hư hỏng do sử dụng sai hướng dẫn, ngấm nước hoặc để ngoài trời mưa nắng.</li>
</ul>

<h2>5. Thời Gian Hoàn Tiền Cho Khách Hàng</h2>
<p>Sau khi bộ phận kho vận tiếp nhận và kiểm định hàng trả về hợp lệ, khoản tiền hoàn lại sẽ được chuyển vào tài khoản ngân hàng của quý khách trong vòng <strong>3 - 5 ngày làm việc</strong>.</p>
HTML,
                'faq_schema' => [
                    [
                        'question' => 'Thời gian giao hàng nội thành TP.HCM và Hà Nội là bao lâu?',
                        'answer' => 'Đối với khu vực nội thành TP.HCM và Hà Nội, thời gian giao hàng tiêu chuẩn là 24 - 48 giờ làm việc, hỗ trợ giao hỏa tốc trong 4 giờ đối với phụ kiện trang trí có sẵn.',
                    ],
                    [
                        'question' => 'Sản phẩm đổi trả cần đáp ứng những điều kiện gì?',
                        'answer' => 'Sản phẩm phải còn nguyên vẹn bao bì đóng gói, đầy đủ phụ kiện, không có dấu hiệu va đập trầy xước do lỗi người dùng và kèm theo hóa đơn mua hàng.',
                    ],
                    [
                        'question' => 'Thời gian hoàn tiền qua tài khoản ngân hàng mất bao lâu?',
                        'answer' => 'Sau khi nhân viên kho nhận và kiểm định hàng trả về hợp lệ, tiền sẽ được hoàn trả về tài khoản ngân hàng của quý khách trong vòng 3 - 5 ngày làm việc.',
                    ],
                ],
            ],
        ];

        foreach ($pagesData as $pg) {
            Page::updateOrCreate(
                ['slug' => $pg['slug']],
                $pg
            );
        }
    }
}
