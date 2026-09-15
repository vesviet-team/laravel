<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoshipService
{
    protected string $baseUrl;

    protected string $token;

    /** Default timeout in seconds for all Goship API calls. */
    protected int $timeout = 8;

    public function __construct()
    {
        $this->baseUrl = config('services.goship.base_url', 'https://api.goship.io/api/v2');
        $this->token = config('services.goship.token', '');
    }

    /**
     * Pre-configured HTTP client with auth token, timeout, and retry.
     */
    protected function http(): PendingRequest
    {
        return Http::withToken($this->token)
            ->timeout($this->timeout)
            ->retry(2, 200, throw: false);
    }

    /**
     * Calculate shipping rates from warehouse to customer address.
     *
     * @param  array  $toAddress  ['city' => string, 'district' => string, 'ward' => string|null]
     * @param  array  $package  ['weight' => int (grams), 'width' => int, 'height' => int, 'length' => int]
     * @return array List of shipping rates from Goship, or empty on failure.
     */
    public function getShippingRates(array $toAddress, array $package = []): array
    {
        $payload = [
            'shipment' => [
                'address_from' => $this->buildWarehouseAddress(),
                'address_to' => [
                    'city' => $toAddress['city'],
                    'district' => $toAddress['district'],
                    'ward' => $toAddress['ward'] ?? null,
                ],
                'parcel' => [
                    'cod' => 0,
                    'weight' => $package['weight'] ?? 1000, // grams; TODO: use product weight field when added
                    'width' => $package['width'] ?? 10,
                    'height' => $package['height'] ?? 10,
                    'length' => $package['length'] ?? 10,
                ],
            ],
        ];

        try {
            $response = $this->http()->post("{$this->baseUrl}/rates", $payload);

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::error('Goship calculate rates failed', ['response' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Goship calculate rates error', ['message' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Create a waybill for the given order via Goship API.
     *
     * @return array|null Goship shipment data, or null on failure.
     */
    public function createWaybill(Order $order): ?array
    {
        // Guard: Prevent creating zero-COD waybill for unpaid online/banking orders
        if ($order->payment_method !== 'cod' && ! $order->isPaid()) {
            Log::warning('Goship create waybill blocked: non-COD order is not paid yet', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
            ]);

            return null;
        }

        $order->loadMissing('items.product');
        $weight = 0;
        foreach ($order->items as $item) {
            $itemWeight = (int) ($item->product?->weight ?? 500);
            $weight += max(100, $itemWeight) * (int) $item->quantity;
        }
        $weight = max(500, $weight);

        $payload = [
            'shipment' => [
                'order_id' => $order->id,
                'address_from' => $this->buildWarehouseAddress(),
                'address_to' => $this->buildCustomerAddress($order),
                'parcel' => [
                    // COD amount = total if payment is COD, otherwise 0.
                    'cod' => $order->payment_method === 'cod' ? (float) $order->total_amount : 0,
                    'weight' => $weight,
                ],
            ],
        ];

        try {
            $response = $this->http()->post("{$this->baseUrl}/shipments", $payload);

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::error('Goship create waybill failed', ['response' => $response->body(), 'order_id' => $order->id]);
        } catch (\Exception $e) {
            Log::error('Goship create waybill error', ['message' => $e->getMessage(), 'order_id' => $order->id]);
        }

        return null;
    }

    /**
     * Build the warehouse (sender) address from config.
     */
    private function buildWarehouseAddress(): array
    {
        return [
            'name' => config('services.goship.warehouse_name', 'My Store'),
            'phone' => config('services.goship.warehouse_phone', '0901234567'),
            'street' => config('services.goship.warehouse_address', ''),
            'city' => config('services.goship.warehouse_city_id', '1'),
            'district' => config('services.goship.warehouse_district_id', '1'),
        ];
    }

    /**
     * Build the customer (recipient) address from order fields.
     * Maps order.customer_name / order.phone / order.address — the actual Order model fields.
     */
    private function buildCustomerAddress(Order $order): array
    {
        return [
            'name' => $order->customer_name,
            'phone' => $order->phone,
            'street' => $order->address,
            'city' => $order->city ?? '',
            'district' => $order->district ?? '',
            'ward' => $order->ward ?? '',
        ];
    }
}
