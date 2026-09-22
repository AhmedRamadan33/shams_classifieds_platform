<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Notifications\ListingRejected;

/**
 * Takes a listing off the site with a mandatory reason the owner can read.
 */
final class RejectListing
{
    public function __invoke(Listing $listing, string $reason): Listing
    {
        $listing->forceFill([
            'status' => ListingStatus::Rejected,
            'rejection_reason' => trim($reason),
            'published_at' => null,
            'expires_at' => null,
        ])->save();

        $listing->user->notify(new ListingRejected($listing, trim($reason)));

        return $listing;
    }
}
