<?php

namespace App\Console\Commands;

use App\Actions\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelExpiredUnpaidOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-expired-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancel unpaid online/banking orders that exceeded payment expiration window';

    /**
     * Execute the console command.
     */
    public function handle(CancelOrderAction $cancelOrderAction): int
    {
        $expiredOrders = Order::query()
            ->where('payment_method', '!=', 'cod')
            ->where('payment_status', 'unpaid')
            ->where('status', OrderStatus::Pending)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->get();

        $count = $expiredOrders->count();

        if ($count === 0) {
            $this->info('No expired unpaid orders found.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} expired unpaid order(s). Processing cancellation...");

        $cancelled = 0;
        foreach ($expiredOrders as $order) {
            try {
                $cancelOrderAction->execute($order);

                $order->update([
                    'payment_status' => 'expired',
                ]);

                Log::info('Order auto-cancelled due to payment expiration', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
                ]);

                $cancelled++;
            } catch (\Throwable $e) {
                Log::error('Failed to auto-cancel expired unpaid order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Successfully cancelled {$cancelled}/{$count} expired orders.");

        return self::SUCCESS;
    }
}
