<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PromotionRule;
use App\Models\PromotionUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('can create and persist promotion usage audit record with casts', function () {
    $rule = PromotionRule::create([
        'name' => 'Test Promo',
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    $usage = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'email' => 'shopper@example.com',
        'discount_amount' => 125000.0,
    ]);

    expect($usage->exists)->toBeTrue();
    expect($usage->id)->toBeGreaterThan(0);
    expect($usage->promotion_rule_id)->toBe($rule->id);
    expect($usage->email)->toBe('shopper@example.com');
    expect($usage->discount_amount)->toBeFloat()->toBe(125000.0);
    expect($usage->created_at)->toBeInstanceOf(Carbon::class);

    $this->assertDatabaseHas('promotion_usages', [
        'id' => $usage->id,
        'promotion_rule_id' => $rule->id,
        'email' => 'shopper@example.com',
        'discount_amount' => 125000.0,
    ]);
});

test('promotion usage belongs to promotion rule', function () {
    $rule = PromotionRule::create([
        'name' => 'Rule Alpha',
        'action_type' => PromotionRule::ACTION_FIXED_AMOUNT,
    ]);

    $usage = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'email' => 'user@example.com',
        'discount_amount' => 30000.0,
    ]);

    expect($usage->promotionRule)->toBeInstanceOf(PromotionRule::class);
    expect($usage->promotion_rule_id)->toBe($rule->id);
    expect($usage->promotionRule->name)->toBe('Rule Alpha');
});

test('promotion usage belongs to customer/user and order optionally', function () {
    $rule = PromotionRule::create([
        'name' => 'VIP Rule',
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    $customer = Customer::create([
        'name' => 'Customer One',
        'email' => 'c1@example.com',
        'password' => 'secret123',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'SO-PROMO-1',
        'status' => OrderStatus::Confirmed,
        'customer_name' => $customer->name,
        'phone' => '0901234567',
        'address' => '123 Main St',
        'email' => $customer->email,
        'subtotal' => 2000000,
        'discount_amount' => 200000,
        'shipping_fee' => 0,
        'total_amount' => 1800000,
    ]);

    $usage = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'email' => $customer->email,
        'discount_amount' => 200000.0,
    ]);

    expect($usage->order)->toBeInstanceOf(Order::class);
    expect($usage->order->id)->toBe($order->id);
    expect($usage->customer)->toBeInstanceOf(Customer::class);
    expect($usage->customer_id)->toBe($customer->id);
});

test('deleting parent promotion rule cascades and purges usage records', function () {
    $rule = PromotionRule::create([
        'name' => 'Temporary Rule',
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    $usage = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'email' => 'buyer@example.com',
        'discount_amount' => 50000.0,
    ]);

    expect(PromotionUsage::where('id', $usage->id)->exists())->toBeTrue();

    $rule->delete();

    expect(PromotionUsage::where('id', $usage->id)->exists())->toBeFalse();
});

test('deleting order cascades and removes associated promotion usage record', function () {
    $rule = PromotionRule::create([
        'name' => 'Order Promo',
        'action_type' => PromotionRule::ACTION_PERCENTAGE,
    ]);

    $order = Order::create([
        'order_number' => 'SO-DEL-1',
        'status' => OrderStatus::Pending,
        'customer_name' => 'Guest Buyer',
        'phone' => '0901234567',
        'address' => '456 Side St',
        'email' => 'orderdel@example.com',
        'subtotal' => 500000,
        'discount_amount' => 50000,
        'shipping_fee' => 30000,
        'total_amount' => 480000,
    ]);

    $usage = PromotionUsage::create([
        'promotion_rule_id' => $rule->id,
        'order_id' => $order->id,
        'email' => $order->email,
        'discount_amount' => 50000.0,
    ]);

    expect(PromotionUsage::where('id', $usage->id)->exists())->toBeTrue();

    $order->delete();

    expect(PromotionUsage::where('id', $usage->id)->exists())->toBeFalse();
});
