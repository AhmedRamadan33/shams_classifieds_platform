<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A messaging action is not allowed. The message is already in Arabic and safe to show to the user.
 */
final class MessagingException extends RuntimeException
{
    public static function ownListing(): self
    {
        return new self(__('app.messages.own_listing'));
    }

    public static function listingUnavailable(): self
    {
        return new self(__('app.messages.listing_unavailable'));
    }

    public static function blockedContent(): self
    {
        return new self(__('app.messages.blocked_content'));
    }
}
