<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;

final class PurgeExpiredListings
{
    /**
     * Permanently delete listings that have been expired for longer than
     * classifieds.purge_expired_after_days. Each one is force-deleted individually so the media
     * library removes its image files too.
     *
     * @return int number of listings deleted
     */
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
