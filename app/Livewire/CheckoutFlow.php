<?php

namespace App\Livewire;

use App\Actions\ProcessCheckoutAction;
use App\Exceptions\CommerceException;
use App\Models\PromotionRule;
use App\Models\Province;
use App\Services\CartService;
use App\Services\GoshipService;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.storefront')]
class CheckoutFlow extends Component
{
    public string $currentStep = 'shipping';

    public array $steps = ['shipping', 'payment', 'review'];

    public array $shippingData = [
        'customer_name' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'city' => '',
        'district' => '',
        'ward' => '',
        'notes' => '',
    ];

    public string $selectedPaymentMethod = 'cod';

    public array $selectedAddress = [];

    public string $couponCode = '';

    public float $couponDiscount = 0.0;

    public ?string $couponApplied = null;

    public ?string $couponError = null;

    public array $cart = [];

    public float $subtotal = 0;

    public ?float $shippingFee = null;

    public bool $isProcessing = false;

    public ?string $errorMessage = null;

    #[Computed]
    public function breakdown()
    {
        $engine = app(PromotionEngine::class);
        $customer = Auth::guard('customer')->user();
        $email = $customer?->email ?? '';

        return $engine->calculateCartDiscounts(
            subtotal: $this->subtotal,
            cartItems: $this->cart,
            couponCode: $this->couponApplied,
            shippingFee: $this->shippingFee ?? 0,
            customer: $customer,
            email: $email
        );
    }

    public function getSelectedMethodDetails(): array
    {
        $methods = [
            'cod' => ['name' => 'Thanh toán khi nhận hàng (COD)', 'description' => 'Bạn thanh toán bằng tiền mặt khi nhận hàng.'],
            'vnpay' => ['name' => 'VNPAY', 'description' => 'Thanh toán qua ứng dụng VNPAY hoặc ngân hàng trực tuyến.'],
            'momo' => ['name' => 'Ví MoMo', 'description' => 'Thanh toán nhanh qua ví điện tử MoMo.'],
            'banking' => ['name' => 'Chuyển khoản ngân hàng', 'description' => 'Chuyển khoản qua Internet Banking hoặc Mobile Banking.'],
        ];

        return $methods[$this->selectedPaymentMethod] ?? ['name' => 'Chưa chọn', 'description' => ''];
    }

    #[Computed]
    public function provinces()
    {
        // PERF-01: Cache via Cache facade (not Livewire persist) — Livewire persist
        // serializes Eloquent Collections to plain arrays which breaks ->name access in views.
        // v4 key: cache as plain array of arrays to bypass strict unserialize() restrictions
        // that cause __PHP_Incomplete_Class, then cast back to objects for Blade.
        $cached = Cache::remember(
            'provinces_list_v4',
            now()->addHours(24),
            fn () => Province::orderBy('name')->get()->toArray()
        );

        return array_map(fn ($item) => (object) $item, $cached);
    }

    #[Computed]
    public function customer()
    {
        return Auth::guard('customer')->user();
    }

    public function mount(): void
    {
        $this->loadCart();

        if (empty($this->cart)) {
            $this->redirectRoute('products.index');
        }

        $customer = Auth::guard('customer')->user();

        // Hydrate a previously applied coupon from the session for any
        // visitor — guests included — so the summary reflects their discount.
        $sessionCoupon = session()->get('coupon');
        if ($sessionCoupon) {
            $this->couponCode = strtoupper(trim($sessionCoupon));
            $this->applyCoupon();
        }

        if ($customer) {
            $this->shippingData['customer_name'] = $customer->name;
            $this->shippingData['phone'] = $customer->phone;
            $this->shippingData['email'] = $customer->email;

            $defaultAddress = $customer->addresses()
                ->where('type', 'shipping')
                ->where('is_default', true)
                ->first();

            if ($defaultAddress) {
                $this->selectedAddress = [
                    'id' => $defaultAddress->id,
                    'recipient_name' => $defaultAddress->recipient_name,
                    'phone' => $defaultAddress->phone,
                    'formatted_address' => $defaultAddress->formatted_address,
                    'address_line_1' => $defaultAddress->address_line_1,
                    'city' => $defaultAddress->city,
                    'district' => $defaultAddress->district,
                    'ward' => $defaultAddress->ward,
                ];

                $this->shippingData['address'] = $defaultAddress->address_line_1;
                $this->shippingData['city'] = $defaultAddress->city;
                $this->shippingData['district'] = $defaultAddress->district;
                $this->shippingData['ward'] = $defaultAddress->ward;
            }
        }

        $this->calculateShippingFee();
    }

    public function loadCart(): void
    {
        $cartService = app(CartService::class);
        $this->cart = $cartService->getCartItemsDetails();
        $this->subtotal = (float) $cartService->calculateTotal();
    }

    public function calculateShippingFee(): void
    {
        try {
            $totalWeight = 0;
            foreach ($this->cart as $item) {
                $productWeight = (int) ($item['weight'] ?? 500);
                $qty = (int) ($item['quantity'] ?? 1);
                $totalWeight += max(100, $productWeight) * $qty;
            }
            $totalWeight = max(500, $totalWeight);

            $goshipService = app(GoshipService::class);
            $rates = $goshipService->getShippingRates(
                [
                    'city' => $this->shippingData['city'] ?? '',
                    'district' => $this->shippingData['district'] ?? '',
                    'ward' => $this->shippingData['ward'] ?? '',
                ],
                ['weight' => $totalWeight]
            );

            $this->shippingFee = isset($rates[0]['total_amount']) ? (float) $rates[0]['total_amount'] : 0;
        } catch (\Throwable $e) {
            Log::warning('Shipping fee calculation failed', ['error' => $e->getMessage()]);
            $this->shippingFee = 0;
        }

        $this->dispatch('shipping-calculated', shippingFee: $this->shippingFee);
    }

    public function applyCoupon(): void
    {
        $this->couponError = null;
        $code = strtoupper(trim($this->couponCode));

        if (empty($code)) {
            $this->couponError = 'Vui lòng nhập mã giảm giá.';

            return;
        }

        $engine = app(PromotionEngine::class);
        $cartService = app(CartService::class);
        $customer = Auth::guard('customer')->user();
        $email = $customer?->email ?? '';

        $breakdown = $engine->calculateCartDiscounts(
            subtotal: $this->subtotal,
            cartItems: $this->cart,
            couponCode: $code,
            shippingFee: $this->shippingFee ?? 0,
            customer: $customer,
            email: $email
        );

        if (! $breakdown->hasCouponApplied() && ! $breakdown->hasDiscount()) {
            $couponRule = PromotionRule::query()
                ->active()
                ->cartRules()
                ->byCode($code)
                ->first();

            if (! $couponRule) {
                $this->couponError = "Mã giảm giá [{$code}] không tồn tại hoặc đã hết hạn.";
            } elseif ($couponRule->min_order_amount > 0 && $this->subtotal < (float) $couponRule->min_order_amount) {
                $gap = (float) $couponRule->min_order_amount - $this->subtotal;
                $this->couponError = "Mã [{$code}] yêu cầu đơn tối thiểu ".number_format($couponRule->min_order_amount, 0, ',', '.').'đ (Cần thêm '.number_format($gap, 0, ',', '.').'đ).';
            } else {
                $this->couponError = "Mã giảm giá [{$code}] không đủ điều kiện áp dụng.";
            }

            $this->couponDiscount = 0;
            $this->couponApplied = null;
            session()->forget('coupon');

            return;
        }

        $this->couponDiscount = $breakdown->couponDiscount;
        $this->couponApplied = $code;
        session()->put('coupon', $code);
        $this->couponError = null;
        $this->dispatch('coupon-applied', discount: $this->couponDiscount);
    }

    public function removeCoupon(): void
    {
        $this->couponCode = '';
        $this->couponDiscount = 0;
        $this->couponApplied = null;
        $this->couponError = null;
        session()->forget('coupon');
        $this->dispatch('coupon-removed');
    }

    public function nextStep(): void
    {
        $currentIndex = array_search($this->currentStep, $this->steps);
        if ($currentIndex !== false && $currentIndex < count($this->steps) - 1) {
            if ($this->validateCurrentStep()) {
                $this->currentStep = $this->steps[$currentIndex + 1];
            }
        }
    }

    public function previousStep(): void
    {
        $currentIndex = array_search($this->currentStep, $this->steps);
        if ($currentIndex !== false && $currentIndex > 0) {
            $this->currentStep = $this->steps[$currentIndex - 1];
        }
    }

    /**
     * Validate the fields required for the current checkout step.
     *
     * Returns true if validation passes or if the step has no validation rules.
     * Throws a Livewire ValidationException if rules are defined and not met.
     *
     * IMPORTANT: The 'review' step intentionally has no validation rules and
     * always returns true. Review is a read-only confirmation step — the user
     * has already validated shipping (step 1) and payment (step 2). Do NOT
     * add logic to 'review' that assumes validation ran; if pre-submit checks
     * are needed, add them to the 'shipping' or 'payment' rule sets instead,
     * or validate them explicitly in submitOrder() before calling this method.
     *
     * @throws ValidationException
     */
    public function validateCurrentStep(): bool
    {
        $this->resetValidation();

        $rules = match ($this->currentStep) {
            'shipping' => [
                'shippingData.customer_name' => ['required', 'string', 'max:255'],
                'shippingData.phone' => ['required', 'string', 'max:20', "regex:/^(\+84|0)[0-9]{9,10}$/"],
                'shippingData.email' => ['nullable', 'email', 'max:255'],
                'shippingData.address' => ['required', 'string', 'max:500'],
                'shippingData.city' => ['required', 'string', 'max:100'],
                'shippingData.district' => ['required', 'string', 'max:100'],
                'shippingData.ward' => ['required', 'string', 'max:100'],
            ],
            'payment' => [
                'selectedPaymentMethod' => ['required', 'in:cod,vnpay,momo,banking,vietqr'],
            ],
            // 'review' is a read-only confirmation step with no user input fields.
            // Validation for shipping and payment data was already completed in prior steps.
            // This empty rules array is intentional — NOT a missing validation case.
            'review' => [],
        };

        // Only call validate() when rules exist. When rules are empty (review step),
        // skip the call entirely rather than letting Livewire validate an empty ruleset
        // (which triggered MissingRulesException in PHP 8 / Livewire 3).
        if (! empty($rules)) {
            $this->validate($rules);
        }

        return true;
    }

    /**
     * Validate all required checkout steps comprehensively before order placement.
     */
    public function validateAllSteps(): bool
    {
        $this->resetValidation();

        $rules = [
            'shippingData.customer_name' => ['required', 'string', 'max:255'],
            'shippingData.phone' => ['required', 'string', 'max:20', "regex:/^(\+84|0)[0-9]{9,10}$/"],
            'shippingData.email' => ['nullable', 'email', 'max:255'],
            'shippingData.address' => ['required', 'string', 'max:500'],
            'shippingData.city' => ['required', 'string', 'max:100'],
            'shippingData.district' => ['required', 'string', 'max:100'],
            'shippingData.ward' => ['required', 'string', 'max:100'],
            'selectedPaymentMethod' => ['required', 'in:cod,vnpay,momo,banking,vietqr'],
        ];

        $this->validate($rules);

        return true;
    }

    #[On('address-changed')]
    public function onAddressChanged(array $address): void
    {
        $this->selectedAddress = $address;

        $this->shippingData['address'] = $address['address_line_1'] ?? '';
        $this->shippingData['city'] = $address['city'] ?? '';
        $this->shippingData['district'] = $address['district'] ?? '';
        $this->shippingData['ward'] = $address['ward'] ?? '';
        $this->shippingData['phone'] = $address['phone'] ?? '';
        $this->shippingData['customer_name'] = $address['recipient_name'] ?? '';

        $this->calculateShippingFee();
    }

    /**
     * [BUG-03 FIX] Sync payment method from PaymentMethodSelector child component.
     * PaymentMethodSelector dispatches 'payment-method-changed' on every selection.
     */
    #[On('payment-method-changed')]
    public function onPaymentMethodChanged(string $method): void
    {
        $allowed = ['cod', 'vnpay', 'momo', 'banking', 'vietqr'];
        if (in_array($method, $allowed)) {
            $this->selectedPaymentMethod = $method;
        }
    }

    /**
     * [BUG-04 FIX] Sync coupon state from CouponInput child component.
     * CouponInput dispatches 'coupon-applied' and 'coupon-removed'.
     */
    #[On('coupon-applied')]
    public function onCouponApplied(float $discount): void
    {
        $this->couponDiscount = $discount;
        $this->couponApplied = session()->get('coupon');
        // Invalidate cached breakdown so order summary recalculates with new coupon
        unset($this->breakdown);
    }

    #[On('coupon-removed')]
    public function onCouponRemoved(): void
    {
        $this->couponDiscount = 0;
        $this->couponApplied = null;
        unset($this->breakdown);
    }

    public function submitOrder(): void
    {
        $this->validateAllSteps();

        $this->isProcessing = true;
        $this->errorMessage = null;

        try {
            $customerData = array_merge(
                $this->shippingData,
                [
                    'payment_method' => $this->selectedPaymentMethod,
                ]
            );

            if (Auth::guard('customer')->check()) {
                $customerData['customer_id'] = Auth::guard('customer')->id();
            }

            $processCheckout = app(ProcessCheckoutAction::class);
            $order = $processCheckout->execute($customerData, $this->couponApplied);

            session()->forget('coupon');

            session()->flash('checkout_completed', $order->order_number);

            $this->redirectRoute('checkout.success', ['order_number' => $order->order_number]);
        } catch (CommerceException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            Log::error('Checkout failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            $this->errorMessage = 'Lỗi xử lý đơn hàng. Vui lòng thử lại hoặc liên hệ hỗ trợ.';
        } finally {
            $this->isProcessing = false;
        }
    }

    public function render()
    {
        return view('livewire.checkout-flow', [
            'provinces' => $this->provinces,
        ]);
    }
}
