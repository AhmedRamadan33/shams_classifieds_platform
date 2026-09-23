<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class ListingLimits
{
    public static function dailyLimitFor(User $user): int
    {
        $subscription = $user->activeSubscription();

        return $subscription !== null
            ? max((int) config('classifieds.daily_listing_limit'), $subscription->plan->daily_listing_limit)
            : (int) config('classifieds.daily_listing_limit');
    }
}
