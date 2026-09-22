<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Paymob (accept.paymob.com), the common gateway for Egyptian merchants. Three chained calls are
 * needed to charge a card: an auth token, an order, then a payment key; the browser is then sent to
 * an iframe hosted by Paymob. See PaymobWebhookController for the server-to-server confirmation and
 * its HMAC verification.
 *
 * Needs real sandbox/production credentials (PAYMOB_API_KEY, PAYMOB_INTEGRATION_ID, PAYMOB_IFRAME_ID,
 * PAYMOB_HMAC_SECRET) to actually charge a card; nothing here can be exercised end-to-end without them,
 * only unit-tested against the documented request/response shapes (see tests/Feature/Payments).
 */
final class PaymobGateway implements PaymentGateway
{
    private const BASE = 'https://accept.paymob.com/api';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $integrationId,
        private readonly string $iframeId,
    ) {}

    public function charge(Payment $payment): PaymentRedirect
    {
        $authToken = $this->authenticate();
        $orderId = $this->createOrder($authToken, $payment);

        $payment->forceFill(['gateway_order_id' => (string) $orderId])->save();

        $paymentKey = $this->requestPaymentKey($authToken, $payment, $orderId);

        return new PaymentRedirect(self::BASE."/acceptance/iframes/{$this->iframeId}?payment_token={$paymentKey}");
    }

    private function authenticate(): string
    {
        $response = $this->post('/auth/tokens', ['api_key' => $this->apiKey]);

        return (string) $this->require($response, 'token');
    }

    private function createOrder(string $authToken, Payment $payment): int
    {
        $response = $this->post('/ecommerce/orders', [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $this->cents($payment),
            'currency' => $payment->currency,
            'merchant_order_id' => 'payment-'.$payment->id,
            'items' => [],
        ]);

        return (int) $this->require($response, 'id');
    }

    private function requestPaymentKey(string $authToken, Payment $payment, int $orderId): string
    {
        $user = $payment->user;
        [$firstName, $lastName] = $this->splitName($user->name);

        $response = $this->post('/acceptance/payment_keys', [
            'auth_token' => $authToken,
            'amount_cents' => $this->cents($payment),
            'expiration' => 3600,
            'order_id' => $orderId,
            'currency' => $payment->currency,
            'integration_id' => $this->integrationId,
            'billing_data' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user->email ?? 'no-reply@example.com',
                'phone_number' => $user->phone,
                'apartment' => 'NA', 'floor' => 'NA', 'street' => 'NA', 'building' => 'NA',
                'city' => 'NA', 'state' => 'NA', 'country' => 'EG', 'postal_code' => 'NA',
            ],
        ]);

        return (string) $this->require($response, 'token');
    }

    // ------------------------------------------------------------------ http

    private function post(string $path, array $payload): Response
    {
        try {
            return Http::asJson()->timeout(15)->post(self::BASE.$path, $payload);
        } catch (ConnectionException $e) {
            throw PaymentGatewayException::provider('paymob', $e->getMessage());
        }
    }

    private function require(Response $response, string $key): mixed
    {
        if ($response->failed() || $response->json($key) === null) {
            throw PaymentGatewayException::provider('paymob', (string) ($response->json('message') ?? $response->body()));
        }

        return $response->json($key);
    }

    private function cents(Payment $payment): int
    {
        return (int) round(((float) $payment->amount) * 100);
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [$name];

        return [$parts[0] !== '' ? $parts[0] : 'NA', $parts[1] ?? 'NA'];
    }
}
