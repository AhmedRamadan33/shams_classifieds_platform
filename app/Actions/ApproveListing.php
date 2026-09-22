<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Notifications\ListingApproved;

/**
 * Publishes a listing: active now, a fresh expiry date, and the owner is told.
 */
final class ApproveListing
{
    public function __invoke(Listing $listing): Listing
    {
        $listing->forceFill([
            'status' => ListingStatus::Active,
            'rejection_reason' => null,
            'published_at' => now(),
            'expires_at' => now()->addDays((int) config('classifieds.listing_duration_days')),
            'expiry_reminded_at' => null,
        ])->save();

        $listing->user->notify(new ListingApproved($listing));

        return $listing;
    }
}
