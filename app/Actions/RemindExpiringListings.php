<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Notifications\ListingExpiringSoon;

final class RemindExpiringListings
{
    public function __invoke(): int
    {
        $sent = 0;

        Listing::query()
            ->with('user')
            ->where('status', ListingStatus::Active->value)
            ->whereNull('expiry_reminded_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays((int) config('classifieds.expiry_reminder_days')))
            ->chunkById(200, function ($listings) use (&$sent): void {
                foreach ($listings as $listing) {
                    $listing->user->notify(new ListingExpiringSoon($listing));
                    $listing->forceFill(['expiry_reminded_at' => now()])->save();
                    $sent++;
                }
            });

        return $sent;
    }
}
