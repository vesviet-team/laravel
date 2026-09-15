<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProcessLandingOrderAction
{
    /**
     * Process an order submitted from a landing page.
     *
     * This Action owns the DB transaction boundary.
     * All DB writes (Order, OrderItem) are atomic.
     *
     * @param  LandingPage  $landingPage  The landing page context.
     * @param  array  $data  Validated form data: name, phone, address, note, selectedComboId.
     * @return Order The created order.
     *
     * @throws RuntimeException on stock or data issues.
     */
    public function execute(LandingPage $landingPage, array $data): Order
    {
        if (! $landingPage->isInStock()) {
            throw new RuntimeException('Sản phẩm hiện tạm hết hàng.');
        }

        $combo = null;
        if (! empty($data['selectedComboId'])) {
            $combo = collect($landingPage->comboRules())
                ->firstWhere('id', $data['selectedComboId']);
        }

        $totalAmount = $combo
            ? (int) ($combo['price'] ?? 0)
            : (int) ($landingPage->product?->price ?? 0);

        $order = DB::transaction(function () use ($landingPage, $data, $combo, $totalAmount) {
            $order = Order::create([
                'landing_page_id' => $landingPage->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'payment_method' => 'cod',
                'customer_name' => $data['name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'notes' => $data['note'] ?: null,
                'subtotal' => $totalAmount,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total_amount' => $totalAmount,
            ]);

            if ($landingPage->product_id) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $landingPage->product_id,
                    'product_variant_id' => null,
                    'product_name' => $combo['name'] ?? $landingPage->product?->name ?? 'Unknown',
                    'variant_name' => null,
                    'sku' => $landingPage->product?->sku ?? null,
                    'quantity' => 1,
                    'price_at_purchase' => $totalAmount,
                    'subtotal' => $totalAmount,
                ]);
            }

            return $order;
        });

        // A1: Fire domain event after transaction commits.
        // Dispatches queued SendOrderConfirmationEmail listener if order has email.
        OrderPlaced::dispatch($order);

        return $order;
    }

    protected function generateOrderNumber(): string
    {
        return 'LP-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
