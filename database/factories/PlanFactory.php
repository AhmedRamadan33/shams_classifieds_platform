<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 50, 500),
            'duration_days' => 30,
            'daily_listing_limit' => 50,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
