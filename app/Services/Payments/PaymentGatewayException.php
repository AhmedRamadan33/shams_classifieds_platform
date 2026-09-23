<?php

declare(strict_types=1);

namespace App\Services\Payments;

use RuntimeException;

final class PaymentGatewayException extends RuntimeException
{
    public static function provider(string $provider, string $reason): self
    {
        return new self("Payment gateway [{$provider}] failed: {$reason}");
    }
}
