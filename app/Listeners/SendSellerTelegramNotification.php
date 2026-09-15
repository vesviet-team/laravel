<?php

namespace App\Listeners;

use App\Events\SellerOrderPlaced;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Telegram notification for Seller-side orders.
 *
 * `NotTenantAware` because the seller identity is carried in the event
 * payload itself; the listener does not need a current tenant to send
 * a notification to the seller's own Telegram chat.
 */
class SendSellerTelegramNotification implements NotTenantAware, ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SellerOrderPlaced $event): void
    {
        $order = $event->order;
        $seller = $event->seller;

        if (empty($seller->telegram_chat_id)) {
            return;
        }

        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) {
            Log::warning(__('seller.telegram.bot_token_missing'));

            return;
        }

        $message = $this->buildMessage($order);

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $seller->telegram_chat_id,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if (! $response->successful()) {
                Log::error('Failed to send Telegram notification', ['response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('Exception sending Telegram notification: '.$e->getMessage());
        }
    }

    private function buildMessage(Order $order): string
    {
        $paymentMethod = $order->payment_method === 'vietqr'
            ? __('seller.telegram.payment_vietqr')
            : __('seller.telegram.payment_cod');

        $message = __('seller.telegram.order_notification_title')."\n\n";
        $message .= __('seller.telegram.order_number', ['order_number' => $order->order_number])."\n";
        $message .= __('seller.telegram.customer', ['name' => $order->customer_name])."\n";
        $message .= __('seller.telegram.phone', ['phone' => $order->phone])."\n";
        $message .= __('seller.telegram.address', ['address' => $order->address])."\n";
        $message .= __('seller.telegram.total', ['total' => $order->formatted_total_amount])."\n";
        $message .= __('seller.telegram.payment_method', ['method' => $paymentMethod])."\n";
        $message .= __('seller.telegram.notes', [
            'notes' => $order->notes ?? __('seller.telegram.notes_empty'),
        ])."\n\n";

        $message .= __('seller.telegram.items_header')."\n";
        foreach ($order->items as $item) {
            $message .= __('seller.telegram.item_line', [
                'name' => $item->product_name,
                'qty' => $item->quantity,
            ])."\n";
        }

        return $message;
    }
}
