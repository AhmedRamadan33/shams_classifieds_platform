<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Where PaymentGateway::charge() sends the browser next: a gateway's hosted payment page/iframe, or
 * (FakePaymentGateway) straight to our own "return" page since the fake gateway settles instantly.
 */
final readonly class PaymentRedirect
{
    public function __construct(public string $url) {}
}
