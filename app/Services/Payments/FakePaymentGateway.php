<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Actions\CompletePayment;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Settles every payment instantly and successfully. Used when no real gateway is configured
 * (PAYMENT_DRIVER=fake, the default): local development, tests, and demos. launch:check fails on it
 * in production.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly CompletePayment $complete) {}

    public function charge(Payment $payment): PaymentRedirect
    {
        $payment->forceFill(['gateway_order_id' => 'FAKE-'.Str::upper(Str::random(10))])->save();

        ($this->complete)($payment, gatewayTransactionId: 'FAKE-TXN-'.$payment->id, meta: ['fake' => true]);

        return new PaymentRedirect(route('payments.show', $payment));
    }
}
