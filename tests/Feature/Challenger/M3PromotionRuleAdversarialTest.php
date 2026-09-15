<?php

use App\Filament\Resources\PromotionRuleResource\Pages\CreatePromotionRule;
use App\Filament\Resources\PromotionRuleResource\Pages\EditPromotionRule;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'challenger_admin@example.com',
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

describe('Empirical Challenge 1: Condition JSON Persistence & Serialization', function () {
    test('cleanly serializes Category IDs, Product IDs, BXGY config, and Tiered steps into JSON column without data loss', function () {
        $category = Category::create(['name' => 'Sofa & Lounge', 'slug' => 'sofa-lounge']);
        $productX = Product::create(['name' => 'Bàn Gỗ Tràm', 'slug' => 'ban-go-tram', 'sku' => 'TAB-01', 'price' => 3500000, 'stock' => 15]);
        $productY = Product::create(['name' => 'Gối Tựa Lưng', 'slug' => 'goi-tua-lung', 'sku' => 'PIL-01', 'price' => 150000, 'stock' => 50]);

        $complexConditions = [
            'description' => 'Điều kiện khuyến mãi đa tầng đặc biệt',
            'category_ids' => [$category->id],
            'product_ids' => [$productX->id, $productY->id],
            'bxgy_config' => [
                'buy_product_id' => $productX->id,
                'buy_quantity' => 2,
                'get_product_id' => $productY->id,
                'get_quantity' => 1,
                'is_free' => false,
                'discount_value' => 50,
                'max_rewards' => 5,
            ],
            'tiered_steps' => [
                ['min_qty' => 3, 'discount_percent' => 5],
                ['min_qty' => 6, 'discount_percent' => 10],
                ['min_qty' => 10, 'discount_percent' => 20],
            ],
        ];

        Livewire::test(CreatePromotionRule::class)
            ->fillForm([
                'name' => 'Complex Promotion Matrix',
                'code' => 'COMPLEX_MATRIX',
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
                'conditions' => $complexConditions,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = PromotionRule::where('code', 'COMPLEX_MATRIX')->first();
        expect($rule)->not->toBeNull();
        expect($rule->conditions)->toBeArray();
        expect($rule->conditions['category_ids'])->toEqual([$category->id]);
        expect($rule->conditions['product_ids'])->toEqual([$productX->id, $productY->id]);
        expect($rule->conditions['bxgy_config']['buy_product_id'])->toBe($productX->id);
        expect($rule->conditions['bxgy_config']['buy_quantity'])->toBe(2);
        expect($rule->conditions['bxgy_config']['get_product_id'])->toBe($productY->id);
        expect($rule->conditions['bxgy_config']['get_quantity'])->toBe(1);
        expect($rule->conditions['bxgy_config']['is_free'])->toBeFalse();
        expect($rule->conditions['bxgy_config']['discount_value'])->toEqual(50);
        expect($rule->conditions['bxgy_config']['max_rewards'])->toEqual(5);
        expect(count($rule->conditions['tiered_steps']))->toBe(3);
        expect($rule->conditions['tiered_steps'][2]['min_qty'])->toBe(10);
        expect($rule->conditions['tiered_steps'][2]['discount_percent'])->toBe(20);
    });
});

describe('Empirical Challenge 2: Form Field Reactivity & Schema Transitions', function () {
    test('switching rule_type to catalog_rule clears coupon code and constrains action_type', function () {
        $component = Livewire::test(CreatePromotionRule::class)
            ->fillForm([
                'code' => 'TEMP_CODE_123',
                'action_type' => PromotionRule::ACTION_BUY_X_GET_Y,
            ])
            ->set('data.rule_type', PromotionRule::RULE_TYPE_CATALOG);

        // Catalog rule resets code to null and resets action_type to percentage
        expect($component->get('data.code'))->toBeNull();
        expect($component->get('data.action_type'))->toBe(PromotionRule::ACTION_PERCENTAGE);
    });

    test('handles all 5 action types cleanly across create and edit flows', function (string $actionType) {
        $rule = PromotionRule::create([
            'name' => 'Dynamic Action Test '.$actionType,
            'code' => 'DYN_'.strtoupper($actionType),
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => $actionType,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        Livewire::test(EditPromotionRule::class, ['record' => $rule->getKey()])
            ->assertSuccessful()
            ->assertFormSet([
                'name' => 'Dynamic Action Test '.$actionType,
                'action_type' => $actionType,
            ]);
    })->with([
        PromotionRule::ACTION_PERCENTAGE,
        PromotionRule::ACTION_FIXED_AMOUNT,
        PromotionRule::ACTION_BUY_X_GET_Y,
        PromotionRule::ACTION_TIERED_QUANTITY,
        PromotionRule::ACTION_FREE_SHIPPING,
    ]);
});

describe('Empirical Challenge 3: Edge Case Inputs & Boundary Conditions', function () {
    test('automatically trims and uppercases coupon codes on dehydration', function () {
        Livewire::test(CreatePromotionRule::class)
            ->fillForm([
                'name' => 'Case Sensitivity Test',
                'code' => '  voucher_lower_10  ',
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_PERCENTAGE,
                'discount_value' => 10,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = PromotionRule::where('name', 'Case Sensitivity Test')->first();
        expect($rule)->not->toBeNull();
        expect($rule->code)->toBe('VOUCHER_LOWER_10');
    });

    test('allows creating automatic rules with null code', function () {
        Livewire::test(CreatePromotionRule::class)
            ->fillForm([
                'name' => 'Automatic Cart Promo',
                'code' => null,
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_PERCENTAGE,
                'discount_value' => 5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = PromotionRule::where('name', 'Automatic Cart Promo')->first();
        expect($rule)->not->toBeNull();
        expect($rule->code)->toBeNull();
        expect($rule->isAutomatic())->toBeTrue();
        expect($rule->isCoupon())->toBeFalse();
    });

    test('rejects duplicate coupon codes regardless of whitespace or casing', function () {
        PromotionRule::create([
            'name' => 'Existing Promo',
            'code' => 'UNIQUE2026',
            'rule_type' => PromotionRule::RULE_TYPE_CART,
            'action_type' => PromotionRule::ACTION_PERCENTAGE,
            'discount_value' => 10,
        ]);

        Livewire::test(CreatePromotionRule::class)
            ->fillForm([
                'name' => 'Duplicate Attempt Promo',
                'code' => 'unique2026',
                'rule_type' => PromotionRule::RULE_TYPE_CART,
                'action_type' => PromotionRule::ACTION_PERCENTAGE,
                'discount_value' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    });
});
