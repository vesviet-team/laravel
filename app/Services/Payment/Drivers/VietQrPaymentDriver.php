<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentGatewayInterface;

class VietQrPaymentDriver implements PaymentGatewayInterface
{
    public function isOffline(): bool
    {
        return false;
    }

    public function process(Order $order): array
    {
        $bankCode = config('services.banking.bank_code', 'MB');
        $accountNo = config('services.banking.account_no', '0123456789');
        $accountName = config('services.banking.account_name', 'MYSTORE');

        $syntax = "ORD {$order->order_number}";
        $info = urlencode($syntax);

        $qrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
            $bankCode,
            $accountNo,
            $order->total_amount,
            $info,
            urlencode($accountName)
        );

        $details = [
            'bank_code' => $bankCode,
            'bank_account_no' => $accountNo,
            'account_name' => $accountName,
            'transfer_syntax' => $syntax,
            'amount' => $order->total_amount,
            'qr_url' => $qrUrl,
        ];

        $order->update([
            'payment_details' => array_merge($order->payment_details ?? [], $details),
        ]);

        return [
            'status' => 'awaiting_transfer',
            'payment_method' => 'vietqr',
            'qr_url' => $qrUrl,
            'instructions' => "Quét mã VietQR hoặc chuyển khoản với nội dung: {$syntax}",
            'metadata' => $details,
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        // For automated bank webhook providers (e.g. Casso / SeVa / Web2M)
        // Can verify signature if secret is present
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
