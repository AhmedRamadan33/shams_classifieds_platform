<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

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
