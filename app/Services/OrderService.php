<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Build an order record and its items, then deduct stock.
     *
     * IMPORTANT: This method MUST be called within an active database transaction.
     * Transaction ownership belongs to the calling Action (ProcessCheckoutAction).
     *
     * Order number uniqueness is enforced by the unique index on the orders table.
     * On the rare collision, the exception propagates — the Action's transaction
     * rolls back and the caller may retry. This is race-safe via DB constraint,
     * not via a check-then-insert loop.
     *
     * @throws \RuntimeException via InventoryService on stock shortfall.
     */
    public function createOrder(
        array $customerData,
        array $cartItems,
        int $subtotal,
        int $discountAmount = 0,
        int $shippingFee = 0
    ): Order {
        $totalAmount = $subtotal - $discountAmount + $shippingFee;

        $paymentMethod = $customerData['payment_method'] ?? 'cod';
        $paymentStatus = $customerData['payment_status'] ?? 'unpaid';
        $paymentExpiresAt = ($paymentMethod !== 'cod') ? now()->addMinutes(15) : null;

        $order = Order::create([
            'customer_id' => $customerData['customer_id'] ?? null,
            'order_number' => $this->generateOrderNumber(),
            'status' => OrderStatus::Pending,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_expires_at' => $paymentExpiresAt,
            'customer_name' => $customerData['customer_name'],
            'phone' => $customerData['phone'],
            'email' => $customerData['email'] ?? null,
            'address' => $customerData['address'],
            'city' => $customerData['city'] ?? null,
            'district' => $customerData['district'] ?? null,
            'ward' => $customerData['ward'] ?? null,
            'notes' => $customerData['notes'] ?? null,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'shipping_fee' => $shippingFee,
            'total_amount' => $totalAmount,
        ]);

        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'] ?? null,
                'sku' => $item['sku'] ?? null,
                'price_at_purchase' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['price'] * $item['quantity'],
                'is_flash_sale' => ! empty($item['is_flash_sale']),
            ]);
        }

        // Load items for InventoryService (needs Eloquent collection)
        $order->load('items');
        $this->inventoryService->deductStock($order);

        return $order;
    }

    /**
     * Generate a unique-by-design order number.
     *
     * Format: ORD-{YYYYMMDD}-{5 random chars}
     * Uniqueness is enforced by the DB unique index on orders.order_number.
     * No check-then-insert loop — that pattern is a race condition.
     */
    protected function generateOrderNumber(): string
    {
        // microsecond timestamp + random suffix gives astronomically low collision chance
        $ts = now()->format('Ymd');
        $random = strtoupper(Str::random(6));

        return "ORD-{$ts}-{$random}";
    }
}
