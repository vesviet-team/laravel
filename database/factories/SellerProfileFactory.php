<?php

namespace Database\Factories;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerProfileFactory extends Factory
{
    protected $model = SellerProfile::class;

    public function definition(): array
    {
        $subdomain = $this->faker->unique()->slug();

        return [
            'user_id' => User::factory(),
            'shop_name' => $this->faker->company(),
            'subdomain' => $subdomain,
            'shop_slug' => $subdomain, // Slice 1: default = subdomain (mirrors migration backfill)
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'status' => 'active',
            'telegram_chat_id' => $this->faker->randomNumber(8, true),
        ];
    }
}
