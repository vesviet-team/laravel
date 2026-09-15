<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatusAction
{
    public function __construct(
        protected CancelOrderAction $cancelOrderAction
    ) {}

    /**
     * Update order status with validation and history logging.
     *
     * @throws Exception
     */
    public function execute(Order $order, OrderStatus|string $newStatus, ?string $note = null): bool
    {
        $newStatusEnum = $newStatus instanceof OrderStatus ? $newStatus : OrderStatus::tryFrom($newStatus);

        if (! $newStatusEnum) {
            throw new Exception('Trạng thái không hợp lệ.');
        }

        $currentStatus = $order->status instanceof OrderStatus ? $order->status : OrderStatus::tryFrom($order->status);

        if ($currentStatus === $newStatusEnum) {
            return true; // No change needed
        }

        if ($currentStatus && ! $currentStatus->canTransitionTo($newStatusEnum)) {
            throw new Exception("Không thể chuyển trạng thái từ '{$currentStatus->label()}' sang '{$newStatusEnum->label()}'.");
        }

        // If transitioning to cancelled, delegate to CancelOrderAction
        if ($newStatusEnum === OrderStatus::Cancelled) {
            $this->cancelOrderAction->execute($order);

            // CancelOrderAction already handles inventory, but we still want to log the history note
            $this->recordHistory($order, $currentStatus, $newStatusEnum, $note);

            return true;
        }

        DB::transaction(function () use ($order, $currentStatus, $newStatusEnum, $note) {
            $order->update(['status' => $newStatusEnum]);
            $this->recordHistory($order, $currentStatus, $newStatusEnum, $note);
        });

        OrderStatusUpdated::dispatch($order, $currentStatus, $newStatusEnum);

        return true;
    }

    protected function recordHistory(Order $order, ?OrderStatus $oldStatus, OrderStatus $newStatus, ?string $note): void
    {
        // Determine the user performing the action
        $userId = auth()->id(); // Works for both Filament web guard and API if authenticated

        $order->histories()->create([
            'seller_id' => $order->seller_id,
            'user_id' => $userId,
            'old_status' => $oldStatus?->value,
            'new_status' => $newStatus->value,
            'note' => $note,
        ]);
    }
}
