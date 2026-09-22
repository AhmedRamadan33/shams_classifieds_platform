<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Thrown when a new code is requested before the resend cooldown has elapsed.
 */
final class OtpCooldownException extends RuntimeException
{
    public function __construct(public readonly int $secondsRemaining)
    {
        parent::__construct("Please wait {$secondsRemaining} seconds before requesting a new code.");
    }
}
