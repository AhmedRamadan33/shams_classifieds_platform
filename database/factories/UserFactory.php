<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+2010'.fake()->unique()->numerify('########'),
            'phone_verified_at' => now(),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'is_banned' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_banned' => true,
        ]);
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles(Role::findOrCreate(User::ROLE_ADMIN, 'web')));
    }

    public function moderator(): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles(Role::findOrCreate(User::ROLE_MODERATOR, 'web')));
    }
}
