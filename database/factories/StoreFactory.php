<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use App\Services\ArabicText;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => ArabicText::slug($name) ?: fake()->unique()->slug(2),
            'bio' => fake()->sentence(),
        ];
    }
}
