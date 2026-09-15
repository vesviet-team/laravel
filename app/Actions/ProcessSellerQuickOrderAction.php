<?php

namespace App\Actions;

use App\Events\SellerOrderPlaced;
use App\Exceptions\SellerActionException;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Services\SellerOrderService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessSellerQuickOrderAction
{
    public function __construct(
        private readonly SellerOrderService $sellerOrderService,
    ) {}

    /**
     * Process quick order from Seller's One-Page Carrd.
     * Enforces ADR-S2: this Action owns the only DB::transaction() boundary.
     * The SellerOrderPlaced event is dispatched AFTER the transaction commits
     * to prevent listeners from observing uncommitted state.
     *
     *
     * @throws SellerActionException
     */
    public function execute(SellerProfile $seller, array $data): Order
    {
        try {
            $order = DB::transaction(function () use ($seller, $data) {
                return $this->sellerOrderService->createQuickOrder($seller, $data);
            });
        } catch (SellerActionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw SellerActionException::orderFailed($e);
        }

        // Dispatch AFTER commit — listeners receive a fully-persisted Order.
        event(new SellerOrderPlaced($order, $seller));

        return $order;
    }
}
