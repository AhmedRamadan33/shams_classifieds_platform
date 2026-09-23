<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function charge(Payment $payment): PaymentRedirect;
}
