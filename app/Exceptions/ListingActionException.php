<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A listing lifecycle action (renew, mark sold) is not allowed in the listing's current state.
 * The message is already in Arabic and safe to show to the user.
 */
final class ListingActionException extends RuntimeException
{
    public static function notRenewable(): self
    {
        return new self(__('app.listing.not_renewable', ['days' => config('classifieds.renew_window_days')]));
    }

    public static function notSellable(): self
    {
        return new self(__('app.listing.not_sellable'));
    }
}
