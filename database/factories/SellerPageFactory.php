<?php

namespace Database\Factories;

use App\Models\SellerPage;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerPage>
 */
class SellerPageFactory extends Factory
{
    protected $model = SellerPage::class;

    public function definition(): array
    {
        return [
            'seller_id' => SellerProfile::factory(),
            'is_published' => true,
            'theme_config' => [
                'primary_color' => '#3b82f6',
                'font' => 'Inter',
                'mode' => 'light',
            ],
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => $this->faker->sentence(4),
                        'subtitle' => $this->faker->sentence(8),
                    ],
                ],
            ],
        ];
    }
}
