<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompletePayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymobWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Server-to-server "transaction processed" callback from Paymob. Excluded from CSRF (bootstrap/app.php)
 * since Paymob, not a browser session, calls it. Every request is verified with the integration's HMAC
 * secret before anything is trusted (see PaymobWebhookVerifier) — the payload is otherwise attacker
 * controlled.
 */
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
            // A failed attempt never overwrites an already-paid payment (e.g. a stray refund callback).
            $payment->forceFill(['status' => PaymentStatus::Failed, 'meta' => $transaction])->save();
        }

        return response('ok', 200);
    }
}
