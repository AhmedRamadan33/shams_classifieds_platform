<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Favorite;

final class FavoriteIds
{
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
