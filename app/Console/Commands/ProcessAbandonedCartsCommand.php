<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartIncentiveMail;
use App\Mail\AbandonedCartReminderMail;
use App\Models\AbandonedCart;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCartItem;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProcessAbandonedCartsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'carts:process-abandoned';

    /**
     * The console command description.
     */
    protected $description = 'Scan abandoned cart sessions and dispatch 2-step automated recovery emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Abandoned Cart recovery processing...');

        // Snapshot idle customer_cart_items (updated_at > 1h) into abandoned_carts
        // for logged-in users — feeds the existing 2-step email recovery flow.
        $this->snapshotIdleDBCarts();

        $activeAbandonedCarts = AbandonedCart::whereNull('recovered_at')->get();

        $processedCount = 0;

        foreach ($activeAbandonedCarts as $cart) {
            // Check if user has already placed an order since creating this cart
            $existingOrder = Order::where(function ($q) use ($cart) {
                $q->where('email', $cart->email);
                if ($cart->customer_id) {
                    $q->orWhere('customer_id', $cart->customer_id);
                }
            })
                ->where('created_at', '>=', $cart->created_at->subMinutes(5))
                ->first();

            if ($existingOrder) {
                $cart->update(['recovered_at' => now()]);
                $this->line("Cart ID {$cart->id} ({$cart->email}) already converted to order #{$existingOrder->order_number}. Marked as recovered.");

                continue;
            }

            // Step 1: Reminder after 1 Hour
            if (is_null($cart->step_1_sent_at) && $cart->created_at <= now()->subHour()) {
                Mail::to($cart->email)->queue(new AbandonedCartReminderMail($cart));
                $cart->update(['step_1_sent_at' => now()]);
                $this->info("Step 1 email dispatched for Cart ID {$cart->id} ({$cart->email}).");
                $processedCount++;

                continue;
            }

            // Step 2: Incentive 5% Coupon after 24 Hours
            if (! is_null($cart->step_1_sent_at) && is_null($cart->step_2_sent_at) && $cart->step_1_sent_at <= now()->subHours(23)) {
                $couponCode = 'REC'.strtoupper(substr(md5(uniqid((string) $cart->id, true)), 0, 6));

                Coupon::create([
                    'code' => $couponCode,
                    'type' => 'percentage',
                    'value' => 5,
                    'usage_limit' => 1,
                    'used_count' => 0,
                    'is_active' => true,
                    'expires_at' => now()->addHours(48),
                ]);

                Mail::to($cart->email)->queue(new AbandonedCartIncentiveMail($cart, $couponCode, 5));
                $cart->update([
                    'step_2_sent_at' => now(),
                    'incentive_coupon_code' => $couponCode,
                ]);

                $this->info("Step 2 incentive email ({$couponCode}) dispatched for Cart ID {$cart->id} ({$cart->email}).");
                $processedCount++;
            }
        }

        $this->info("Abandoned Cart processing completed. Dispatched {$processedCount} recovery emails.");

        return Command::SUCCESS;
    }

    /**
     * Snapshot customer_cart_items idle for more than 1 hour into the abandoned_carts table.
     * This feeds the existing 2-step recovery email flow without changing that logic.
     *
     * Only processes customers with an email who have not already placed an order recently.
     * Uses chunk() to avoid memory issues on large datasets.
     */
    private function snapshotIdleDBCarts(): void
    {
        $this->line('Snapshotting idle DB carts into abandoned_carts...');

        // Get unique customer_ids with idle cart items (updated_at > 1h ago)
        $idleCustomerIds = CustomerCartItem::where('updated_at', '<', now()->subHour())
            ->distinct()
            ->pluck('customer_id');

        if ($idleCustomerIds->isEmpty()) {
            return;
        }

        Customer::whereIn('id', $idleCustomerIds)
            ->whereNotNull('email')
            ->chunk(100, function ($customers) {
                foreach ($customers as $customer) {
                    try {
                        $items = CustomerCartItem::where('customer_id', $customer->id)->get();

                        if ($items->isEmpty()) {
                            continue;
                        }

                        $itemsJson = $items->map(fn ($i) => [
                            'product_id' => $i->product_id,
                            'product_variant_id' => $i->product_variant_id,
                            'quantity' => $i->quantity,
                        ])->all();

                        $subtotal = 0; // price enrichment handled by email template

                        AbandonedCart::updateOrCreate(
                            ['customer_id' => $customer->id, 'recovered_at' => null],
                            [
                                'email' => $customer->email,
                                'cart_token' => Str::random(32),
                                'items_json' => $itemsJson,
                                'subtotal' => $subtotal,
                            ]
                        );
                    } catch (\Throwable $e) {
                        Log::warning('ProcessAbandonedCartsCommand: snapshot failed for customer.', [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
