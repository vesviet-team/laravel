<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by UpdateSellerOrderStatusAction after a successful status change commit.
 *
 * P1-01: Replaces the missing event that EditSellerOrder never dispatched.
 * Listeners can use this event to:
 *   - Send Telegram notifications to the seller
 *   - Send order status emails to customers
 *   - Log the audit trail of status changes
 *
 * The event carries both the old and new status so listeners can
 * react to specific transitions (e.g. only notify on Shipped).
 */
class SellerOrderStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  Order  $order  The updated order (post-save, refreshed).
     * @param  OrderStatus  $oldStatus  The status before the transition.
     * @param  OrderStatus  $newStatus  The status after the transition.
     */
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $oldStatus,
        public readonly OrderStatus $newStatus,
    ) {}
}
