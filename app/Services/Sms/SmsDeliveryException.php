<?php

declare(strict_types=1);

namespace App\Services\Sms;

use RuntimeException;

/**
 * The SMS provider rejected or could not deliver a message.
 */
final class SmsDeliveryException extends RuntimeException
{
    public static function provider(string $provider, string $reason): self
    {
        return new self("SMS delivery via {$provider} failed: {$reason}");
    }
}
