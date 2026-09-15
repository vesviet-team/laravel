<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'post_category_id' => PostCategory::factory(),
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'excerpt' => fake()->paragraph(),
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'featured_image' => null,
            'banner_image' => null,
            'status' => 'published',
            'published_at' => now(),
            'is_featured' => false,
            'reading_time_minutes' => 1,
            'meta_title' => $title,
            'meta_description' => fake()->sentence(),
            'meta_keywords' => 'furniture, scandinavian, design',
            'canonical_url' => null,
            'schema_type' => 'Article',
            'faq_schema' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now()->addDays(2),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
