<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Actions\CompletePayment;
use App\Models\Payment;
use Illuminate\Support\Str;

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
