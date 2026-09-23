<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;

final class PurgeExpiredListings
{
    public function __invoke(): int
    {
        $cutoff = now()->subDays((int) config('classifieds.purge_expired_after_days'));
        $deleted = 0;

        Listing::withTrashed()
            ->where('status', ListingStatus::Expired->value)
            ->where('expires_at', '<=', $cutoff)
            ->chunkById(100, function ($listings) use (&$deleted): void {
                foreach ($listings as $listing) {
                    $listing->forceDelete();
                    $deleted++;
                }
            });

        return $deleted;
    }
}
