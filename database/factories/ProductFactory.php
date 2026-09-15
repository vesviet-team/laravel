<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.uniqid(),
            'sku' => 'SKU-'.$this->faker->unique()->randomNumber(5),
            'description' => $this->faker->paragraph,
            'price' => $this->faker->numberBetween(100, 1000) * 1000,
            'stock' => $this->faker->numberBetween(10, 100),
            // products.status ENUM: ['draft', 'published', 'archived'] — 'active' is NOT valid.
            // SQLite ignores ENUM constraints; MySQL 8.0 strict mode throws SQLSTATE[01000] Warning 1265.
            'status' => 'published',

            'is_visible' => true,
            'is_purchasable' => true,
            'seller_id' => SellerProfile::factory(),
        ];
    }
}
