<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'category_slug' => null,
            'governorate_slug' => null,
            'filters' => [],
            'notify' => false,
            'last_notified_at' => null,
        ];
    }
}
