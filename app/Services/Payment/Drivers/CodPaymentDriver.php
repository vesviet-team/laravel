<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentGatewayInterface;

class CodPaymentDriver implements PaymentGatewayInterface
{
    public function isOffline(): bool
    {
        return true;
    }

    public function process(Order $order): array
    {
        return [
            'status' => 'pending_cod',
            'payment_method' => 'cod',
            'instructions' => 'Thanh toán tiền mặt khi nhân viên giao hàng.',
            'metadata' => [
                'collect_amount' => $order->total_amount,
            ],
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        return true;
    }

    public function markPaid(Order $order, string $transactionId, array $payload = []): void
    {
        $order->update([
            'payment_status' => 'paid',
            'payment_transaction_id' => $transactionId,
            'paid_at' => now(),
            'payment_details' => array_merge($order->payment_details ?? [], $payload),
        ]);
    }
}
