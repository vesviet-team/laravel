<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\SellerOrderStatusUpdated;
use App\Exceptions\SellerActionException;
use App\Models\Order;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Update an Order's status on behalf of a Seller, enforcing the state machine.
 *
 * P1-01 fix: Previously, EditSellerOrder used Filament's default EditRecord path
 * (direct Eloquent save). That means the state machine was enforced only in the
 * UI dropdown — a crafted POST could jump any state. This Action provides:
 *   - Server-side state machine validation (OrderStatus::canTransitionTo())
 *   - A single transaction boundary (ADR-S2)
 *   - SellerOrderStatusUpdated event dispatch after commit
 *
 * ADR-S2: This Action owns the sole DB::transaction() boundary.
 * ADR-S3 Trust Zone: Standard — requires PR review.
 *
 * @throws SellerActionException
 */
class UpdateSellerOrderStatusAction
{
    /**
     * Update the order status for a seller, validating the state machine transition.
     *
     * @param  SellerProfile  $seller  The active seller tenant.
     * @param  Order  $order  The order to update (must belong to $seller).
     * @param  OrderStatus  $newStatus  The requested new status.
     * @param  string|null  $notes  Optional internal notes from the seller.
     *
     * @throws SellerActionException When the transition is invalid or not owned by the seller.
     */
    public function execute(
        SellerProfile $seller,
        Order $order,
        OrderStatus $newStatus,
        ?string $notes = null,
    ): Order {
        // 1. Ownership check — redundant with SellerOrderPolicy but defense-in-depth.
        if ((int) $order->seller_id !== (int) $seller->id) {
            throw SellerActionException::unauthorized('Đơn hàng này không thuộc gian hàng của bạn.');
        }

        // 2. Ensure status has been cast to the enum (should always be true given Order::$casts).
        $currentStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($currentStatus === null) {
            throw SellerActionException::invalidStatusTransition('unknown', $newStatus->value);
        }

        // 3. State machine: validate the requested transition.
        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw SellerActionException::invalidStatusTransition($currentStatus->value, $newStatus->value);
        }

        // 4. Apply update inside a transaction (ADR-S2).
        try {
            $updatedOrder = DB::transaction(function () use ($order, $newStatus, $notes): Order {
                $order->status = $newStatus;

                if ($notes !== null) {
                    $order->notes = $notes;
                }

                $order->save();

                return $order->refresh();
            });
        } catch (SellerActionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SellerActionException(
                'Không thể cập nhật trạng thái đơn hàng: '.$e->getMessage(),
                'order_status_update_failed',
                $e,
            );
        }

        // 5. Dispatch event AFTER commit — allows listeners (Telegram, email) to react.
        event(new SellerOrderStatusUpdated($updatedOrder, $currentStatus, $newStatus));

        return $updatedOrder;
    }
}
