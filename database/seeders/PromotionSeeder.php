<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionRule;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Seed 6 realistic Scandinavian promotion campaigns.
     * Idempotent: safe to run multiple times without duplicating records.
     */
    public function run(): void
    {
        // ── Dynamic Resolution: Ensure required category exists for catalog promotion ──
        $lightingCategory = Category::where('slug', 'den-chieu-sang')->first()
            ?? Category::where('name', 'like', '%Đèn%')->first()
            ?? Category::firstOrCreate(
                ['slug' => 'den-chieu-sang'],
                [
                    'name' => 'Đèn Chiếu Sáng',
                    'description' => 'Đèn thả trần nghệ thuật, đèn bàn xi măng và đèn sàn trang trí tinh tế.',
                ]
            );

        // ── Dynamic Resolution: Ensure required products exist for BXGY promotion ──
        $desk = Product::where('slug', 'copenhague-desk')->first()
            ?? Product::where('sku', 'DSK-005')->first()
            ?? Product::where('name', 'like', '%Bàn Làm Việc%')->first()
            ?? Product::firstOrCreate(
                ['slug' => 'copenhague-desk'],
                [
                    'name' => 'Bàn Làm Việc Copenhague Desk',
                    'sku' => 'DSK-005',
                    'price' => 14200000,
                    'stock' => 12,
                    'weight' => 18000,
                    'status' => 'published',
                    'is_featured' => true,
                ]
            );

        $chair = Product::where('slug', 'synnes-dining-chair')->first()
            ?? Product::where('sku', 'CHR-004')->first()
            ?? Product::where('name', 'like', '%Ghế Ăn%')->first()
            ?? Product::firstOrCreate(
                ['slug' => 'synnes-dining-chair'],
                [
                    'name' => 'Ghế Ăn Gỗ Sồi Synnes Dining Chair',
                    'sku' => 'CHR-004',
                    'price' => 5800000,
                    'stock' => 20,
                    'weight' => 4500,
                    'status' => 'published',
                    'is_featured' => true,
                ]
            );

        // ── 1. Campaign 1: WELCOME10 ─────────────────────────────────────────
        // Coupon WELCOME10, cart rule, percentage 10%, max discount 500,000đ, min order 300,000đ, per_customer_limit = 1
        PromotionRule::updateOrCreate(
            ['name' => 'WELCOME10 - Ưu Đãi Chào Mừng Khách Hàng Mới (Giảm 10%)'],
            [
                'name' => 'WELCOME10 - Ưu Đãi Chào Mừng Khách Hàng Mới (Giảm 10%)',
                'code' => 'WELCOME10',
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_PERCENTAGE,
                'discount_value' => 10.0,
                'max_discount_amount' => 500000.0,
                'min_order_amount' => 300000.0,
                'min_quantity' => 0,
                'conditions' => null,
                'target_customer_tier' => PromotionRule::TIER_ALL,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'priority' => 10,
                'stop_further_rules' => false,
                'is_active' => true,
            ]
        );

        // ── 2. Campaign 2: TIERED_PROMO ──────────────────────────────────────
        // Automatic cart rule, tiered_quantity strategy, tiered_steps: 2 items (5%), 4 items (10%), 6 items (15%)
        PromotionRule::updateOrCreate(
            ['name' => 'TIERED_PROMO - Chiết Khấu Bậc Thang Số Lượng'],
            [
                'name' => 'TIERED_PROMO - Chiết Khấu Bậc Thang Số Lượng',
                'code' => null,
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
                'discount_value' => 5.0,
                'max_discount_amount' => null,
                'min_order_amount' => 0.0,
                'min_quantity' => 0,
                'conditions' => [
                    'tiered_steps' => [
                        ['min_qty' => 2, 'discount_value' => 5, 'discount_percent' => 5],
                        ['min_qty' => 4, 'discount_value' => 10, 'discount_percent' => 10],
                        ['min_qty' => 6, 'discount_value' => 15, 'discount_percent' => 15],
                    ],
                ],
                'target_customer_tier' => PromotionRule::TIER_ALL,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'priority' => 20,
                'stop_further_rules' => false,
                'is_active' => true,
            ]
        );

        // ── 3. Campaign 3: BUY_DESK_GET_CHAIR ────────────────────────────────
        // Automatic cart rule, buy_x_get_y strategy with bxgy_config (trigger desk, reward chair at 100% free / 50%)
        PromotionRule::updateOrCreate(
            ['name' => 'BUY_DESK_GET_CHAIR - Mua Bàn Làm Việc Tặng Ghế Ăn Bắc Âu'],
            [
                'name' => 'BUY_DESK_GET_CHAIR - Mua Bàn Làm Việc Tặng Ghế Ăn Bắc Âu',
                'code' => null,
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
                'discount_value' => 100.0,
                'max_discount_amount' => null,
                'min_order_amount' => 0.0,
                'min_quantity' => 0,
                'conditions' => [
                    'bxgy_config' => [
                        'buy_product_id' => $desk->id,
                        'buy_quantity' => 1,
                        'get_product_id' => $chair->id,
                        'get_quantity' => 1,
                        'discount_percent' => 100,
                        'discount_value' => 100,
                        'is_free' => true,
                    ],
                    'trigger_product_ids' => [$desk->id],
                    'reward_product_id' => $chair->id,
                    'reward_discount_percent' => 100,
                    'buy_product_id' => $desk->id,
                    'buy_quantity' => 1,
                    'get_product_id' => $chair->id,
                    'get_quantity' => 1,
                    'discount_percent' => 100,
                    'is_free' => true,
                ],
                'target_customer_tier' => PromotionRule::TIER_ALL,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'priority' => 30,
                'stop_further_rules' => false,
                'is_active' => true,
            ]
        );

        // ── 4. Campaign 4: CATALOG_LIGHTING_15 ───────────────────────────────
        // Automatic catalog rule, percentage 15%, applicable to Lighting category
        PromotionRule::updateOrCreate(
            ['name' => 'CATALOG_LIGHTING_15 - Giảm 15% Bộ Sưu Tập Đèn Chiếu Sáng'],
            [
                'name' => 'CATALOG_LIGHTING_15 - Giảm 15% Bộ Sưu Tập Đèn Chiếu Sáng',
                'code' => null,
                'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
                'action_type' => PromotionRule::ACTION_PERCENTAGE,
                'discount_value' => 15.0,
                'max_discount_amount' => null,
                'min_order_amount' => 0.0,
                'min_quantity' => 0,
                'conditions' => [
                    'category_ids' => [$lightingCategory->id],
                ],
                'target_customer_tier' => PromotionRule::TIER_ALL,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'priority' => 5,
                'stop_further_rules' => false,
                'is_active' => true,
            ]
        );

        // ── 5. Campaign 5: FREESHIP500 ───────────────────────────────────────
        // Automatic cart rule, free_shipping strategy, min order 500,000đ
        PromotionRule::updateOrCreate(
            ['name' => 'FREESHIP500 - Miễn Phí Vận Chuyển Đơn Hàng Từ 500.000₫'],
            [
                'name' => 'FREESHIP500 - Miễn Phí Vận Chuyển Đơn Hàng Từ 500.000₫',
                'code' => null,
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
                'discount_value' => 0.0,
                'max_discount_amount' => null,
                'min_order_amount' => 500000.0,
                'min_quantity' => 0,
                'conditions' => null,
                'target_customer_tier' => PromotionRule::TIER_ALL,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'priority' => 50,
                'stop_further_rules' => false,
                'is_active' => true,
            ]
        );

        // ── 6. Campaign 6: VIPGOLD20 ─────────────────────────────────────────
        // Coupon or cart rule for Gold/Diamond tiers, 20% percentage discount, max discount 1,000,000đ, target_customer_tier: vip_gold
        PromotionRule::updateOrCreate(
            ['name' => 'VIPGOLD20 - Đặc Quyền Giảm 20% Thành Viên VIP Gold'],
            [
                'name' => 'VIPGOLD20 - Đặc Quyền Giảm 20% Thành Viên VIP Gold',
                'code' => 'VIPGOLD20',
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_PERCENTAGE,
                'discount_value' => 20.0,
                'max_discount_amount' => 1000000.0,
                'min_order_amount' => 0.0,
                'min_quantity' => 0,
                'conditions' => null,
                'target_customer_tier' => 'vip_gold',
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'priority' => 5,
                'stop_further_rules' => false,
                'is_active' => true,
            ]
        );

        // ── [ADR-B2] Legacy Combo Fallback Rule (I-05) ───────────────────────
        // This rule makes the hardcoded 5% combo discount (0.05 magic number) traceable
        // and auditable as a real PromotionRule DB record.
        //
        // MIGRATION PLAN:
        //   Phase 1 (now): Seed this rule as INACTIVE. The legacy code path in
        //     App\Services\PromotionEngine still handles it via the hardcoded 0.05 fallback.
        //   Phase 2 (next sprint): Activate this rule. Verify checkout tests pass.
        //   Phase 3 (after Phase 2): Remove the hardcoded 0.05 fallback from
        //     App\Services\PromotionEngine::calculateDiscount() (lines 72-73 and 105-106).
        //
        // This is the Source of Truth for the "buy 2+ items, get 5% off" promotion.
        // Discount cap: applied only to eligible (non-flash-sale) items subtotal.
        PromotionRule::updateOrCreate(
            ['name' => '[LEGACY] Combo 2+ Sản Phẩm Giảm 5%'],
            [
                'name' => '[LEGACY] Combo 2+ Sản Phẩm Giảm 5%',
                'code' => null,        // automatic rule — no coupon required
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_PERCENTAGE,
                'discount_value' => 5.0,         // 5% — was magic number 0.05 in code
                'max_discount_amount' => null,        // no cap — matches legacy behavior
                'min_order_amount' => 0.0,
                'min_quantity' => 2,           // requires 2+ eligible items
                'conditions' => null,        // all categories
                'target_customer_tier' => PromotionRule::TIER_ALL,
                'usage_limit' => null,
                'usage_limit_per_user' => 0,           // unlimited
                'priority' => 100,         // lowest priority — fallback only
                'stop_further_rules' => false,
                // INACTIVE until Phase 3 migration completes (legacy code removed first)
                'is_active' => false,
            ]
        );
    }
}
