<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;

final class ExpireListings
{
    /**
     * Flag every active listing whose expiry date has passed as expired.
     *
     * @return int number of listings that were expired
     */
    public function __invoke(): int
    {
        $expired = Listing::query()
            ->where('status', ListingStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => ListingStatus::Expired->value]);

        if ($expired > 0) {
            Listing::flushHomeCache(); // a bulk update fires no model events
        }

        return $expired;
    }
}
