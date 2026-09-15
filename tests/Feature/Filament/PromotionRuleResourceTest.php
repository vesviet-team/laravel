<?php

use App\Filament\Resources\PromotionRuleResource\Pages\CreatePromotionRule;
use App\Filament\Resources\PromotionRuleResource\Pages\EditPromotionRule;
use App\Filament\Resources\PromotionRuleResource\Pages\ListPromotionRules;
use App\Filament\Resources\PromotionRuleResource\RelationManagers\PromotionUsagesRelationManager;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Models\PromotionUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $permissions = [
        'view_any_promotion::rule',
        'view_promotion::rule',
        'create_promotion::rule',
        'update_promotion::rule',
        'delete_promotion::rule',
        'delete_any_promotion::rule',
        'reorder_promotion::rule',
    ];

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);
});

// ==========================================
// 1. List Page & Tab Filtering Tests
// ==========================================

test('can render promotion rule list page and see records in table', function () {
    $rule = PromotionRule::create([
        'name' => 'Giảm 10% Chào Bạn Mới',
        'code' => 'WELCOME10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
        'is_active' => true,
        'priority' => 1,
    ]);

    Livewire::test(ListPromotionRules::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$rule]);
});

test('can filter promotion rules by 5 navigation tabs', function () {
    $cartRule = PromotionRule::create([
        'name' => 'Khuyến Mãi Giỏ Hàng Tự Động',
        'code' => null,
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 5,
        'is_active' => true,
    ]);

    $catalogRule = PromotionRule::create([
        'name' => 'Giảm Giá Danh Mục Đèn',
        'code' => null,
        'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 15,
        'is_active' => true,
    ]);

    $couponRule = PromotionRule::create([
        'name' => 'Mã Giảm Giá VIP',
        'code' => 'VIPGOLD20',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 20,
        'is_active' => true,
    ]);

    $bxgyRule = PromotionRule::create([
        'name' => 'Mua Bàn Tặng Ghế',
        'code' => null,
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
        'discount_value' => 100,
        'is_active' => true,
    ]);

    // Tab 'all' sees all 4
    Livewire::test(ListPromotionRules::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$cartRule, $catalogRule, $couponRule, $bxgyRule]);

    // Tab 'cart_rules'
    Livewire::test(ListPromotionRules::class)
        ->set('activeTab', 'cart_rules')
        ->assertCanSeeTableRecords([$cartRule, $couponRule, $bxgyRule])
        ->assertCanNotSeeTableRecords([$catalogRule]);

    // Tab 'catalog_rules'
    Livewire::test(ListPromotionRules::class)
        ->set('activeTab', 'catalog_rules')
        ->assertCanSeeTableRecords([$catalogRule])
        ->assertCanNotSeeTableRecords([$cartRule, $couponRule, $bxgyRule]);

    // Tab 'coupons'
    Livewire::test(ListPromotionRules::class)
        ->set('activeTab', 'coupons')
        ->assertCanSeeTableRecords([$couponRule])
        ->assertCanNotSeeTableRecords([$cartRule, $catalogRule, $bxgyRule]);

    // Tab 'bxgy'
    Livewire::test(ListPromotionRules::class)
        ->set('activeTab', 'bxgy')
        ->assertCanSeeTableRecords([$bxgyRule])
        ->assertCanNotSeeTableRecords([$cartRule, $catalogRule, $couponRule]);
});

test('can search promotion rules by name and coupon code in table', function () {
    $rule1 = PromotionRule::create([
        'name' => 'Flash Sale Cuối Tuần',
        'code' => 'WEEKEND50',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
        'discount_value' => 50000,
    ]);

    $rule2 = PromotionRule::create([
        'name' => 'Khuyến Mãi Mùa Thu',
        'code' => 'AUTUMN10',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
    ]);

    Livewire::test(ListPromotionRules::class)
        ->searchTable('WEEKEND50')
        ->assertCanSeeTableRecords([$rule1])
        ->assertCanNotSeeTableRecords([$rule2]);

    Livewire::test(ListPromotionRules::class)
        ->searchTable('Mùa Thu')
        ->assertCanSeeTableRecords([$rule2])
        ->assertCanNotSeeTableRecords([$rule1]);
});

// ==========================================
// 2. Create Page & Strategy Validation Tests
// ==========================================

test('can create a percentage cart rule with max cap and min order subtotal via filament form', function () {
    Livewire::test(CreatePromotionRule::class)
        ->fillForm([
            'name' => 'Giảm 15% Tối Đa 300K Cho Đơn Từ 2 Triệu',
            'code' => 'SALE15CAP',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 15,
            'max_discount_amount' => 300000,
            'min_order_amount' => 2000000,
            'min_quantity' => 1,
            'target_customer_tier' => 'all',
            'usage_limit' => 100,
            'usage_limit_per_user' => 1,
            'priority' => 5,
            'stop_further_rules' => true,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('promotion_rules', [
        'name' => 'Giảm 15% Tối Đa 300K Cho Đơn Từ 2 Triệu',
        'code' => 'SALE15CAP',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 15.00,
        'max_discount_amount' => 300000.00,
        'min_order_amount' => 2000000.00,
        'usage_limit' => 100,
        'stop_further_rules' => true,
    ]);
});

test('can create a catalog price rule with category conditions', function () {
    $category = Category::create([
        'name' => 'Sofa Phòng Khách',
        'slug' => 'sofa-phong-khach',
    ]);

    Livewire::test(CreatePromotionRule::class)
        ->fillForm([
            'name' => 'Giảm 10% Toàn Bộ Danh Mục Sofa',
            'code' => null,
            'rule_type' => PromotionRule::RULE_TYPE_CATALOG,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 10,
            'conditions' => [
                'category_ids' => [$category->id],
            ],
            'is_active' => true,
            'priority' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = PromotionRule::where('name', 'Giảm 10% Toàn Bộ Danh Mục Sofa')->first();
    expect($rule)->not->toBeNull();
    expect($rule->rule_type)->toBe(PromotionRule::RULE_TYPE_CATALOG);
    expect($rule->conditions['category_ids'])->toEqual([$category->id]);
});

test('can create a buy x get y promotion rule with nested bxgy config', function () {
    $productX = Product::create([
        'name' => 'Bàn Làm Việc Bắc Âu',
        'slug' => 'ban-lam-viec-bac-au',
        'sku' => 'DSK-001',
        'price' => 2500000,
        'stock' => 10,
    ]);

    $productY = Product::create([
        'name' => 'Ghế Xoay Ergonomic',
        'slug' => 'ghe-xoay-ergonomic',
        'sku' => 'CHR-001',
        'price' => 800000,
        'stock' => 20,
    ]);

    Livewire::test(CreatePromotionRule::class)
        ->fillForm([
            'name' => 'Mua 2 Bàn Làm Việc Tặng 1 Ghế Xoay',
            'code' => 'BUY2GET1',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            'conditions' => [
                'bxgy_config' => [
                    'buy_product_id' => $productX->id,
                    'buy_quantity' => 2,
                    'get_product_id' => $productY->id,
                    'get_quantity' => 1,
                    'is_free' => true,
                ],
            ],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = PromotionRule::where('code', 'BUY2GET1')->first();
    expect($rule)->not->toBeNull();
    expect($rule->action_type)->toBe(PromotionRule::ACTION_BUY_X_GET_Y);
    expect($rule->conditions['bxgy_config']['buy_product_id'])->toBe($productX->id);
    expect($rule->conditions['bxgy_config']['buy_quantity'])->toBe(2);
});

test('can create a tiered quantity rule with stepped volume tiers', function () {
    Livewire::test(CreatePromotionRule::class)
        ->fillForm([
            'name' => 'Chiết Khấu Số Lượng Đèn Bàn',
            'code' => null,
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_TIERED_QUANTITY,
            'discount_value' => 0,
            'conditions' => [
                'tiered_steps' => [
                    ['min_qty' => 2, 'discount_percent' => 5],
                    ['min_qty' => 4, 'discount_percent' => 10],
                    ['min_qty' => 6, 'discount_percent' => 15],
                ],
            ],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = PromotionRule::where('name', 'Chiết Khấu Số Lượng Đèn Bàn')->first();
    expect($rule)->not->toBeNull();
    expect($rule->action_type)->toBe(PromotionRule::ACTION_TIERED_QUANTITY);
    expect(count($rule->conditions['tiered_steps']))->toBe(3);
    expect($rule->conditions['tiered_steps'][1]['min_qty'])->toBe(4);
});

test('can create a free shipping promotion rule', function () {
    Livewire::test(CreatePromotionRule::class)
        ->fillForm([
            'name' => 'Freeship Toàn Quốc Đơn Từ 500K',
            'code' => 'FREESHIP500',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
            'discount_value' => 0,
            'min_order_amount' => 500000,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('promotion_rules', [
        'code' => 'FREESHIP500',
        'action_type' => PromotionRule::ACTION_FREE_SHIPPING,
        'min_order_amount' => 500000.00,
    ]);
});

test('validates required fields on create promotion rule form', function () {
    Livewire::test(CreatePromotionRule::class)
        ->fillForm([
            'name' => null,
            'discount_value' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
        ]);
});

test('rejects duplicate coupon code on create promotion rule', function () {
    PromotionRule::create([
        'name' => 'Khuyến Mãi Ban Đầu',
        'code' => 'DUPLICATE_CODE',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
    ]);

    Livewire::test(CreatePromotionRule::class)
        ->fillForm([
            'name' => 'Khuyến Mãi Trùng Mã',
            'code' => 'DUPLICATE_CODE',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 15,
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

// ==========================================
// 3. Edit & Deletion Tests
// ==========================================

test('can edit an existing promotion rule and update values and conditions', function () {
    $rule = PromotionRule::create([
        'name' => 'Rule Ban Đầu',
        'code' => 'OLDCODE',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
        'priority' => 10,
        'is_active' => false,
    ]);

    Livewire::test(EditPromotionRule::class, ['record' => $rule->getKey()])
        ->fillForm([
            'name' => 'Rule Đã Cập Nhật',
            'code' => 'NEWCODE',
            'discount_value' => 25,
            'priority' => 1,
            'is_active' => true,
            'conditions' => [
                'category_ids' => [5, 6],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $rule->refresh();
    expect($rule->name)->toBe('Rule Đã Cập Nhật');
    expect($rule->code)->toBe('NEWCODE');
    expect($rule->discount_value)->toEqual(25.00);
    expect($rule->priority)->toBe(1);
    expect($rule->is_active)->toBeTrue();
    expect($rule->conditions['category_ids'])->toEqual([5, 6]);
});

test('can delete a promotion rule via table delete action', function () {
    $rule = PromotionRule::create([
        'name' => 'Rule Cần Xóa',
        'code' => 'DELETE_ME',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
    ]);

    Livewire::test(ListPromotionRules::class)
        ->callTableAction('delete', $rule)
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseMissing('promotion_rules', [
        'id' => $rule->id,
    ]);
});

test('can bulk delete promotion rules via table bulk action', function () {
    $rule1 = PromotionRule::create([
        'name' => 'Bulk Delete 1',
        'code' => 'BULK1',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
    ]);

    $rule2 = PromotionRule::create([
        'name' => 'Bulk Delete 2',
        'code' => 'BULK2',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 15,
    ]);

    Livewire::test(ListPromotionRules::class)
        ->callTableBulkAction('delete', [$rule1, $rule2])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseMissing('promotion_rules', ['id' => $rule1->id]);
    $this->assertDatabaseMissing('promotion_rules', ['id' => $rule2->id]);
});

// ==========================================
// 4. Relation Manager (Usage History) Tests
// ==========================================

test('can render promotion usages relation manager on edit page and see usage logs', function () {
    $rule = PromotionRule::create([
        'name' => 'Mã Khuyến Mãi Đã Dùng',
        'code' => 'USED_PROMO',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
    ]);

    $customer = Customer::create([
        'name' => 'Nguyễn Văn An',
        'email' => 'an.nguyen@example.com',
        'password' => 'password123',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-2026-001',
        'customer_name' => 'Nguyễn Văn An',
        'email' => 'an.nguyen@example.com',
        'phone' => '0901234567',
        'address' => '123 Lê Lợi',
        'city' => 'Hồ Chí Minh',
        'district' => 'Quận 1',
        'ward' => 'Bến Nghé',
        'subtotal' => 1000000,
        'discount_amount' => 100000,
        'shipping_fee' => 30000,
        'total_amount' => 930000,
    ]);

    $usage = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'email' => 'an.nguyen@example.com',
        'discount_amount' => 100000,
    ]);

    Livewire::test(PromotionUsagesRelationManager::class, [
        'ownerRecord' => $rule,
        'pageClass' => EditPromotionRule::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$usage]);
});

test('can search usage records in relation manager by email', function () {
    $rule = PromotionRule::create([
        'name' => 'Rule Đa Lượt Dùng',
        'code' => 'MULTI_USE',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
    ]);

    $usage1 = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'email' => 'customer.alpha@example.com',
        'discount_amount' => 50000,
    ]);

    $usage2 = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'email' => 'customer.beta@example.com',
        'discount_amount' => 80000,
    ]);

    Livewire::test(PromotionUsagesRelationManager::class, [
        'ownerRecord' => $rule,
        'pageClass' => EditPromotionRule::class,
    ])
        ->searchTable('customer.alpha@example.com')
        ->assertCanSeeTableRecords([$usage1])
        ->assertCanNotSeeTableRecords([$usage2]);
});

test('promotion usages relation manager is strictly read only without create or delete actions', function () {
    $rule = PromotionRule::create([
        'name' => 'Rule Kiểm Tra Read Only',
        'code' => 'READONLY_TEST',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 10,
    ]);

    Livewire::test(PromotionUsagesRelationManager::class, [
        'ownerRecord' => $rule,
        'pageClass' => EditPromotionRule::class,
    ])
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('delete');
});

// ==========================================
// 5. Shield RBAC & Authorization Tests
// ==========================================

test('unauthorized user without permissions cannot view promotion rule list page', function () {
    $unauthorizedUser = User::factory()->create([
        'email' => 'staff_no_access@example.com',
    ]);

    $this->actingAs($unauthorizedUser);

    Livewire::test(ListPromotionRules::class)
        ->assertForbidden();
});

test('user with only view_any permission can view table but cannot access create page', function () {
    $viewerUser = User::factory()->create([
        'email' => 'viewer@example.com',
    ]);

    Permission::findOrCreate('view_any_promotion::rule', 'web');
    $viewerUser->givePermissionTo('view_any_promotion::rule');

    $this->actingAs($viewerUser);

    Livewire::test(ListPromotionRules::class)
        ->assertSuccessful();

    Livewire::test(CreatePromotionRule::class)
        ->assertForbidden();
});

test('super_admin bypasses all permission gates without explicit permissions', function () {
    $superAdminRole = Role::findOrCreate('super_admin', 'web');

    $superAdmin = User::factory()->create([
        'email' => 'superadmin@example.com',
    ]);
    $superAdmin->assignRole($superAdminRole);

    $this->actingAs($superAdmin);

    $rule = PromotionRule::create([
        'name' => 'Super Admin Promo',
        'code' => 'SUPER_ADMIN_CODE',
        'rule_type' => PromotionRule::RULE_TYPE_CART,
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
        'discount_value' => 50,
    ]);

    // Can access list
    Livewire::test(ListPromotionRules::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$rule]);

    // Can access create
    Livewire::test(CreatePromotionRule::class)
        ->assertSuccessful();

    // Can access edit
    Livewire::test(EditPromotionRule::class, ['record' => $rule->getKey()])
        ->assertSuccessful();
});
