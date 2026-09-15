<?php

namespace App\Livewire;

use App\Services\Promotions\DTOs\PromotionDiscountBreakdown;
use Livewire\Component;

class OrderSummary extends Component
{
    public array $breakdown = [];

    public float $subtotal = 0;

    public ?float $shippingFee = null;

    public bool $showFreeGifts = true;

    public bool $showPromotions = true;

    public bool $showShipping = true;

    public string $theme = 'light';

    protected $listeners = [
        'coupon-applied' => 'onCouponApplied',
        'coupon-removed' => 'onCouponRemoved',
        'shipping-calculated' => 'onShippingCalculated',
    ];

    public function mount(
        array|PromotionDiscountBreakdown $breakdown,
        float $subtotal = 0,
        ?float $shippingFee = null,
        bool $showFreeGifts = true,
        bool $showPromotions = true,
        bool $showShipping = true,
        string $theme = 'light'
    ): void {
        $this->breakdown = $breakdown instanceof PromotionDiscountBreakdown ? $breakdown->toArray() : $breakdown;
        $this->subtotal = $subtotal;
        $this->shippingFee = $shippingFee;
        $this->showFreeGifts = $showFreeGifts;
        $this->showPromotions = $showPromotions;
        $this->showShipping = $showShipping;
        $this->theme = $theme;
    }

    public function onCouponApplied(array $event): void
    {
        $this->dispatch('refresh-summary', subtotal: $this->subtotal, shippingFee: $this->shippingFee);
    }

    public function onCouponRemoved(): void
    {
        $this->dispatch('refresh-summary', subtotal: $this->subtotal, shippingFee: $this->shippingFee);
    }

    public function onShippingCalculated(float $shippingFee): void
    {
        $this->shippingFee = $shippingFee;
        $this->dispatch('refresh-summary', subtotal: $this->subtotal, shippingFee: $shippingFee);
    }

    public function render()
    {
        return view('livewire.order-summary');
    }
}
