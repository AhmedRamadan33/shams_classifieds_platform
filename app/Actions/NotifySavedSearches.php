<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatched;

final class NotifySavedSearches
{
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
