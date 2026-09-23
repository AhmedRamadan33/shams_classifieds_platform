<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompletePayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymobWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymobWebhookController extends Controller
{
    public function __invoke(Request $request, CompletePayment $complete): Response
    {
        $transaction = (array) $request->input('obj', []);
        $hmac = (string) $request->query('hmac', '');

        $verifier = new PaymobWebhookVerifier((string) config('services.paymob.hmac_secret'));

        if (! $verifier->verify($transaction, $hmac)) {
            return response('invalid signature', 403);
        }

        $orderId = (string) data_get($transaction, 'order.id');
        $payment = Payment::query()->where('gateway_order_id', $orderId)->first();

        if ($payment === null) {
            return response('unknown order', 404);
        }

        if (data_get($transaction, 'success') === true) {
            $complete($payment, gatewayTransactionId: (string) data_get($transaction, 'id'), meta: $transaction);
        } elseif (! $payment->isPaid()) {
            $payment->forceFill(['status' => PaymentStatus::Failed, 'meta' => $transaction])->save();
        }

        return response('ok', 200);
    }
}
