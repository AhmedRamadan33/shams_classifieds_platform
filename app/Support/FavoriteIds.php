<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Favorite;

/**
 * The ids of the signed-in user's favorite listings, loaded once per request so a page with
 * many listing cards does not run a query per card. Registered as a scoped singleton.
 */
final class FavoriteIds
{
    /** @var array<int, true>|null */
    private ?array $ids = null;

    public function has(int $listingId): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $this->ids ??= Favorite::query()
            ->where('user_id', $user->id)
            ->pluck('listing_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        return isset($this->ids[$listingId]);
    }
}
