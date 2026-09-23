<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

class HeroSlideFactory extends Factory
{
    protected $model = HeroSlide::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(6),
            'link_url' => fake()->url(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
