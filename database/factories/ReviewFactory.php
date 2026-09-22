<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reviewer_id' => User::factory(),
            'seller_id' => User::factory(),
            'listing_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
            'is_hidden' => false,
        ];
    }
}
