<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * How many listings a user may create per rolling 24 hours: the site default
 * (classifieds.daily_listing_limit), or their subscription plan's higher limit while it is active.
 */
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
