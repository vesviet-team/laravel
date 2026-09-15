<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * A1/A3: Send order confirmation email asynchronously via the database queue.
 *
 * This listener replaces the inline Mail::to()->send() call that was in
 * CheckoutController::store(). By implementing ShouldQueue, this runs in the
 * background via `php artisan queue:work` — checkout response is not blocked by SMTP.
 *
 * ADR-03: database queue driver — no Redis required, works on shared hosting.
 *
 * `NotTenantAware` because the order's email is a property of the order
 * itself; we do not need a current tenant to deliver a transactional email.
 */
class SendOrderConfirmationEmail implements NotTenantAware, ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Number of times the job may be attempted before failing.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying (exponential via $backoff).
     *
     * @var array<int>
     */
    public array $backoff = [30, 60, 120];

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        if (empty($order->email)) {
            return; // Customer did not provide email — skip silently.
        }

        try {
            Mail::to($order->email)->send(new OrderConfirmationMail($order));
        } catch (\Throwable $e) {
            Log::error('Order confirmation email failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'email' => $order->email,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger the retry mechanism (up to $tries attempts).
            throw $e;
        }
    }

    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        Log::error('Order confirmation email permanently failed', [
            'order_id' => $event->order->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
