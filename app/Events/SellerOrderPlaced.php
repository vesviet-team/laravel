<?php

namespace App\Events;

use App\Models\Order;
use App\Models\SellerProfile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellerOrderPlaced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    public SellerProfile $seller;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, SellerProfile $seller)
    {
        $this->order = $order;
        $this->seller = $seller;
    }
}
