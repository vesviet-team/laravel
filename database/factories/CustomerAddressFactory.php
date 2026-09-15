<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => $this->faker->randomElement(['shipping', 'billing']),
            'label' => $this->faker->optional(0.5)->randomElement(['Nhà riêng', 'Văn phòng', 'Nhà người thân', 'Kho hàng']),
            'recipient_name' => $this->faker->name(),
            'phone' => $this->faker->numerify('09########'),
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => $this->faker->optional(0.3)->buildingNumber(),
            'city' => $this->faker->city(),
            'district' => $this->faker->citySuffix(),
            'ward' => $this->faker->optional(0.5)->streetSuffix(),
            'postal_code' => $this->faker->optional(0.3)->postcode(),
            'country' => 'Vietnam',
            'is_default' => false,
            'metadata' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'shipping',
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'billing',
        ]);
    }
}
