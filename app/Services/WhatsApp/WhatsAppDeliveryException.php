<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use RuntimeException;

final class WhatsAppDeliveryException extends RuntimeException
{
    public static function provider(string $provider, string $reason): self
    {
        return new self("WhatsApp gateway [{$provider}] failed: {$reason}");
    }
}
