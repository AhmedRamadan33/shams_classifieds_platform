<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentRedirect
{
    public function __construct(public string $url) {}
}
