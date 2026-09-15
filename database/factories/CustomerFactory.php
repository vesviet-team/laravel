<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->numerify('09########'),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'remember_token' => Str::random(10),
            'avatar' => null,
            'date_of_birth' => $this->faker->optional(0.3)->dateTimeBetween('-60 years', '-18 years'),
            'gender' => $this->faker->optional(0.5)->randomElement(['male', 'female', 'other']),
            'referral_code' => $this->faker->unique()->optional(0.3)->bothify('REF-????##'),
            'referred_by' => null,
            'loyalty_points' => $this->faker->numberBetween(0, 50000),
            'email_verified_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'notification_preferences' => [
                'order_updates' => true,
                'promotions' => true,
                'security_alerts' => true,
                'newsletter' => $this->faker->boolean(50),
            ],
            'privacy_consent' => [
                'terms_accepted_at' => now()->toISOString(),
                'privacy_policy_version' => '1.0',
                'marketing_consent' => $this->faker->boolean(60),
            ],
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);
    }

    public function referredBy(Customer $referrer): static
    {
        return $this->state(fn (array $attributes) => [
            'referred_by' => $referrer->id,
        ]);
    }
}
