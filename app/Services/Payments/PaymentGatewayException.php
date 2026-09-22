<?php

declare(strict_types=1);

namespace App\Services\Payments;

use RuntimeException;

/**
 * Thrown when a payment gateway cannot be reached or rejects the request. Caught by
 * FeaturedPurchaseController, which shows a friendly error and leaves the payment "pending" (the
 * user can try again; nothing was charged).
 */
final class PaymentGatewayException extends RuntimeException
{
    public static function provider(string $provider, string $reason): self
    {
        return new self("Payment gateway [{$provider}] failed: {$reason}");
    }
}
