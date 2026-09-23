<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdBannerStatus;
use App\Enums\AdPlacement;
use App\Models\AdBanner;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdBannerFactory extends Factory
{
    protected $model = AdBanner::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'placement' => fake()->randomElement(AdPlacement::cases())->value,
            'title' => fake()->words(3, true),
            'target_url' => fake()->url(),
            'status' => AdBannerStatus::Pending->value,
        ];
    }

    public function targetingListing(Listing $listing): static
    {
        return $this->state([
            'user_id' => $listing->user_id,
            'listing_id' => $listing->id,
            'target_url' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(['status' => AdBannerStatus::Approved->value]);
    }

    public function active(): static
    {
        return $this->state([
            'status' => AdBannerStatus::Active->value,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(6),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => AdBannerStatus::Rejected->value,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => AdBannerStatus::Expired->value,
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);
    }
}
