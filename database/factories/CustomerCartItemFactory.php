<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerCartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerCartItemFactory extends Factory
{
    protected $model = CustomerCartItem::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'quantity' => fake()->numberBetween(1, 5),
            'updated_at' => now(),
        ];
    }
}
