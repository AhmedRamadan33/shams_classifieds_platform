<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdPlacement;
use App\Models\AdPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdPackageFactory extends Factory
{
    protected $model = AdPackage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'placement' => fake()->randomElement(AdPlacement::cases())->value,
            'duration_days' => 7,
            'price' => fake()->randomFloat(2, 50, 500),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
