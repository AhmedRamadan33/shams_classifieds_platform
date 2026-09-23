<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Exceptions\ListingActionException;
use App\Models\Listing;

final class RenewListing
{
    public function canRenew(Listing $listing): bool
    {
        if ($listing->isExpired()) {
            return true;
        }

        return $listing->status === ListingStatus::Active
            && $listing->expires_at !== null
            && $listing->expires_at->lte(now()->addDays((int) config('classifieds.renew_window_days')));
    }

    public function __invoke(Listing $listing): Listing
    {
        if (! $this->canRenew($listing)) {
            throw ListingActionException::notRenewable();
        }

        $listing->forceFill([
            'status' => ListingStatus::Active,
            'expires_at' => now()->addDays((int) config('classifieds.listing_duration_days')),
            'expiry_reminded_at' => null,
        ])->save();

        return $listing;
    }
}
