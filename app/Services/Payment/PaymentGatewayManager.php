<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Drivers\CodPaymentDriver;
use App\Services\Payment\Drivers\VietQrPaymentDriver;

class PaymentGatewayManager
{
    /**
     * Resolve gateway driver by payment method name.
     */
    public function driver(string $method): PaymentGatewayInterface
    {
        return match (strtolower(trim($method))) {
            'cod' => app(CodPaymentDriver::class),
            'vietqr', 'banking' => app(VietQrPaymentDriver::class),
            default => app(CodPaymentDriver::class),
        };
    }

    /**
     * Process order payment instructions via the appropriate gateway driver.
     */
    public function process(Order $order): array
    {
        $driver = $this->driver($order->payment_method ?? 'cod');

        return $driver->process($order);
    }
}
