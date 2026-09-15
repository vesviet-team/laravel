<?php

namespace App\Livewire;

use Livewire\Component;

class PaymentMethodSelector extends Component
{
    public string $selectedMethod = 'cod';

    public array $availableMethods = [];

    public bool $showDetails = false;

    public array $methodDetails = [
        'cod' => [
            'name' => 'Thanh toán khi nhận hàng (COD)',
            'description' => 'Bạn thanh toán bằng tiền mặt khi nhận hàng.',
            'icon' => 'cash',
            'fee' => 0,
            'enabled' => true,
        ],
        'vnpay' => [
            'name' => 'VNPAY',
            'description' => 'Thanh toán qua ứng dụng VNPAY hoặc ngân hàng trực tuyến.',
            'icon' => 'qrcode',
            'fee' => 0,
            'enabled' => true,
        ],
        'momo' => [
            'name' => 'Ví MoMo',
            'description' => 'Thanh toán nhanh qua ví điện tử MoMo.',
            'icon' => 'mobile',
            'fee' => 0,
            'enabled' => true,
        ],
        'banking' => [
            'name' => 'Chuyển khoản ngân hàng',
            'description' => 'Chuyển khoản qua Internet Banking hoặc Mobile Banking.',
            'icon' => 'bank',
            'fee' => 0,
            'enabled' => true,
        ],
    ];

    public function mount(array $availableMethods = ['cod']): void
    {
        $this->availableMethods = $availableMethods;

        // Ensure first available method is selected
        if (! in_array($this->selectedMethod, $availableMethods)) {
            $this->selectedMethod = $availableMethods[0] ?? 'cod';
        }
    }

    public function selectMethod(string $method): void
    {
        if (! in_array($method, $this->availableMethods)) {
            return;
        }

        $this->selectedMethod = $method;
        $this->dispatch('payment-method-changed', method: $method, details: $this->methodDetails[$method] ?? []);
    }

    public function toggleDetails(): void
    {
        $this->showDetails = ! $this->showDetails;
    }

    public function getSelectedMethodDetails(): array
    {
        return $this->methodDetails[$this->selectedMethod] ?? [];
    }

    public function isMethodEnabled(string $method): bool
    {
        return in_array($method, $this->availableMethods) && ($this->methodDetails[$method]['enabled'] ?? false);
    }

    public function render()
    {
        return view('livewire.payment-method-selector');
    }
}
