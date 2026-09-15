<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Livewire\Component;

class AddressSelector extends Component
{
    public Customer $customer;

    public array $selectedAddress = [];

    public string $addressType = 'shipping'; // shipping | billing

    public bool $showNewAddressForm = false;

    public array $newAddress = [
        'type' => 'shipping',
        'label' => '',
        'recipient_name' => '',
        'phone' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'city' => '',
        'district' => '',
        'ward' => '',
        'postal_code' => '',
        'country' => 'Vietnam',
        'is_default' => false,
    ];

    public array $addresses = [];

    protected $listeners = [
        'addressSaved' => 'loadAddresses',
        'addressSelected' => 'onAddressSelected',
    ];

    public function mount(Customer $customer, string $addressType = 'shipping'): void
    {
        $this->customer = $customer;
        $this->addressType = $addressType;
        $this->newAddress['type'] = $addressType;
        $this->loadAddresses();
    }

    public function loadAddresses(): void
    {
        $this->addresses = $this->customer
            ->addresses()
            ->where('type', $this->addressType)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'label' => $a->label,
                'type' => $a->type,
                'recipient_name' => $a->recipient_name,
                'phone' => $a->phone,
                'formatted_address' => $a->formatted_address,
                'is_default' => $a->is_default,
                'address_line_1' => $a->address_line_1,
                'address_line_2' => $a->address_line_2,
                'city' => $a->city,
                'district' => $a->district,
                'ward' => $a->ward,
                'postal_code' => $a->postal_code,
                'country' => $a->country,
            ])
            ->toArray();

        // Auto-select default address if none selected
        if (empty($this->selectedAddress) && ! empty($this->addresses)) {
            $default = collect($this->addresses)->firstWhere('is_default', true);
            $this->selectedAddress = $default ?? $this->addresses[0];
        }
    }

    public function selectAddress(array $address): void
    {
        $this->selectedAddress = $address;
        // Livewire v3: dispatch() broadcasts to all components (replaces emitUp)
        $this->dispatch('address-changed', address: $address);
    }

    public function onAddressSelected(array $address): void
    {
        $this->selectedAddress = $address;
        $this->dispatch('address-changed', address: $address);
    }

    public function toggleNewAddressForm(): void
    {
        $this->showNewAddressForm = ! $this->showNewAddressForm;
        if ($this->showNewAddressForm) {
            $this->resetNewAddressForm();
        }
    }

    public function saveNewAddress(): void
    {
        $this->validate([
            'newAddress.recipient_name' => ['required', 'string', 'max:255'],
            'newAddress.phone' => ['required', 'string', 'max:20', "regex:/^(\+84|0)[0-9]{9,10}$/"],
            'newAddress.address_line_1' => ['required', 'string', 'max:500'],
            'newAddress.city' => ['required', 'string', 'max:100'],
            'newAddress.district' => ['required', 'string', 'max:100'],
            'newAddress.ward' => ['required', 'string', 'max:100'],
        ]);

        $data = $this->newAddress;
        $data['customer_id'] = $this->customer->id;

        // If this is the first address of this type, make it default
        $existingCount = $this->customer->addresses()->where('type', $data['type'])->count();
        if ($existingCount === 0) {
            $data['is_default'] = true;
        }

        CustomerAddress::create($data);

        $this->resetNewAddressForm();
        $this->showNewAddressForm = false;
        $this->loadAddresses();

        $this->dispatch('address-saved');
    }

    public function setDefault(int $addressId): void
    {
        $address = $this->customer->addresses()->find($addressId);
        if ($address) {
            $address->update(['is_default' => true]);
            $this->loadAddresses();
        }
    }

    public function deleteAddress(int $addressId): void
    {
        $address = $this->customer->addresses()->find($addressId);
        if ($address) {
            $wasDefault = $address->is_default;
            $type = $address->type;
            $address->delete();

            // If deleted was default, set next as default
            if ($wasDefault) {
                $next = $this->customer->addresses()->where('type', $type)->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }

            $this->loadAddresses();
            $this->dispatch('address-saved');
        }
    }

    public function resetNewAddressForm(): void
    {
        $this->newAddress = [
            'type' => $this->addressType,
            'label' => '',
            'recipient_name' => '',
            'phone' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'district' => '',
            'ward' => '',
            'postal_code' => '',
            'country' => 'Vietnam',
            'is_default' => false,
        ];
    }

    public function render()
    {
        return view('livewire.address-selector');
    }
}
