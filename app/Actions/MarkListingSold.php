<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Exceptions\ListingActionException;
use App\Models\Listing;

final class MarkListingSold
{
    public function __invoke(Listing $listing): Listing
    {
        if ($listing->status !== ListingStatus::Active) {
            throw ListingActionException::notSellable();
        }

        $listing->forceFill(['status' => ListingStatus::Sold])->save();

        return $listing;
    }
}
