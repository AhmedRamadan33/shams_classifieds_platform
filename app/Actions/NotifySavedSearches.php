<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatched;

/**
 * For every saved search with notify=true, checks for listings published since it was last checked
 * (or since it was created, the first time) and notifies the owner once if there are any.
 * last_notified_at only advances on an actual notification, so a quiet run never causes a later
 * batch of new listings to be missed.
 */
final class NotifySavedSearches
{
    /**
     * @return int number of notifications sent
     */
    public function __invoke(): int
    {
        $sent = 0;

        SavedSearch::query()->where('notify', true)->with('user')->chunkById(100, function ($searches) use (&$sent): void {
            foreach ($searches as $search) {
                $since = $search->last_notified_at ?? $search->created_at;

                $count = $search->listingSearch()->query()->where('listings.published_at', '>', $since)->count();

                if ($count > 0) {
                    $search->user->notify(new SavedSearchMatched($search, $count));
                    $search->forceFill(['last_notified_at' => now()])->save();
                    $sent++;
                }
            }
        });

        return $sent;
    }
}
