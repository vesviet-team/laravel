<?php

namespace App\Livewire;

use App\Models\PromotionRule;
use App\Services\CartService;
use App\Services\PromotionEngine;
use Livewire\Component;

class CouponInput extends Component
{
    public string $couponCode = '';

    public float $discount = 0.0;

    public ?string $couponApplied = null;

    public ?string $errorMessage = null;

    public float $subtotal = 0.0;

    public function mount(float $subtotal, PromotionEngine $promotionEngine, CartService $cartService): void
    {
        $this->subtotal = $subtotal;

        // Restore any previously applied coupon from session
        $sessionCoupon = session()->get('coupon');
        if ($sessionCoupon) {
            $this->couponCode = strtoupper(trim($sessionCoupon));
            $this->recomputeDiscount($promotionEngine, $cartService);
        }
    }

    public function applyCoupon(CartService $cartService, PromotionEngine $promotionEngine): void
    {
        $this->errorMessage = null;

        $code = strtoupper(trim($this->couponCode));

        if (empty($code)) {
            $this->errorMessage = 'Vui lòng nhập mã giảm giá.';

            return;
        }

        $cartItems = $cartService->getCartItemsDetails();
        $customer = auth('customer')->user();
        $email = $customer?->email ?? '';

        $breakdown = $promotionEngine->calculateCartDiscounts(
            subtotal: $this->subtotal,
            cartItems: $cartItems,
            couponCode: $code,
            shippingFee: 0.0,
            customer: $customer,
            email: $email
        );

        if (! $breakdown->hasCouponApplied() && ! $breakdown->hasDiscount()) {
            $couponRule = PromotionRule::query()->active()->cartRules()->byCode($code)->first();
            if (! $couponRule) {
                $this->errorMessage = "Mã giảm giá [{$code}] không tồn tại hoặc đã hết hạn.";
            } elseif ($couponRule->min_order_amount > 0 && $this->subtotal < (float) $couponRule->min_order_amount) {
                $gap = (float) $couponRule->min_order_amount - $this->subtotal;
                $this->errorMessage = "Mã [{$code}] yêu cầu đơn tối thiểu ".number_format($couponRule->min_order_amount, 0, ',', '.').'₫ (Cần thêm '.number_format($gap, 0, ',', '.').'₫).';
            } else {
                $this->errorMessage = "Mã giảm giá [{$code}] không đủ điều kiện áp dụng.";
            }

            $this->discount = 0.0;
            $this->couponApplied = null;
            session()->forget('coupon');

            return;
        }

        $this->discount = $breakdown->couponDiscount;
        $this->couponApplied = $code;
        session()->put('coupon', $code);

        $this->dispatch('coupon-applied', discount: $this->discount, breakdown: $breakdown->toArray());
    }

    public function removeCoupon(): void
    {
        $this->couponCode = '';
        $this->discount = 0.0;
        $this->couponApplied = null;
        $this->errorMessage = null;
        session()->forget('coupon');

        $this->dispatch('coupon-removed');
    }

    /**
     * [I-01] Recompute discount using method-injected dependencies.
     * No app() service locator — services passed by caller.
     */
    private function recomputeDiscount(PromotionEngine $promotionEngine, CartService $cartService): void
    {
        $cartItems = $cartService->getCartItemsDetails();
        $customer = auth('customer')->user();
        $email = $customer?->email ?? '';

        $breakdown = $promotionEngine->calculateCartDiscounts(
            subtotal: $this->subtotal,
            cartItems: $cartItems,
            couponCode: $this->couponCode,
            shippingFee: 0.0,
            customer: $customer,
            email: $email
        );

        if ($breakdown->hasCouponApplied() || $breakdown->couponDiscount > 0) {
            $this->discount = $breakdown->couponDiscount;
            $this->couponApplied = $this->couponCode;
        } else {
            session()->forget('coupon');
            $this->couponCode = '';
            $this->couponApplied = null;
            $this->discount = 0.0;
        }
    }

    public function render()
    {
        return view('livewire.coupon-input');
    }
}
