<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'excerpt' => fake()->paragraph(),
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'featured_image' => null,
            'is_published' => true,
            'published_at' => now(),
            'template' => 'default',
            'meta_title' => $title,
            'meta_description' => fake()->sentence(),
            'canonical_url' => null,
            'schema_type' => 'WebPage',
            'faq_schema' => null,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    public function policy(): static
    {
        return $this->state(fn (array $attributes) => [
            'template' => 'policy',
        ]);
    }
}
