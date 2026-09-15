<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => Banner::POSITION_HERO_SLIDER,
            'title' => fake()->sentence(3),
            'eyebrow' => strtoupper(fake()->words(2, true)),
            'subtitle' => fake()->paragraph(1),
            'image' => 'banners/sample-'.fake()->numberBetween(1, 5).'.jpg',
            'link' => '/catalog',
            'cta_text' => 'Khám Phá Ngay',
            'open_in_new_tab' => false,
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => fake()->numberBetween(1, 10),
            'clicks_count' => fake()->numberBetween(0, 500),
        ];
    }

    /**
     * Hero slider position state.
     */
    public function hero(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Banner::POSITION_HERO_SLIDER,
        ]);
    }

    /**
     * Home 2-column promo position state.
     */
    public function promo2Col(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Banner::POSITION_HOME_PROMO_2COL,
        ]);
    }

    /**
     * Home 3-column collection position state.
     */
    public function collection3Col(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Banner::POSITION_HOME_COLLECTION_3COL,
        ]);
    }

    /**
     * Catalog header position state.
     */
    public function catalogHeader(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Banner::POSITION_CATALOG_HEADER,
        ]);
    }

    /**
     * Blog sidebar position state.
     */
    public function blogSidebar(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Banner::POSITION_BLOG_SIDEBAR,
        ]);
    }

    /**
     * Top announcement position state.
     */
    public function topAnnouncement(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Banner::POSITION_TOP_ANNOUNCEMENT,
        ]);
    }

    /**
     * Active status state without date constraints.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    /**
     * Inactive status state.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Scheduled future active state (starts in future).
     */
    public function scheduledFuture(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(10),
        ]);
    }

    /**
     * Expired active state (ends in past).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(2),
        ]);
    }
}
