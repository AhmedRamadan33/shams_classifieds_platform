<?php

declare(strict_types=1);

use App\Actions\CompletePayment;
use App\Enums\PaymentStatus;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymobGateway;
use App\Services\Payments\PaymobWebhookVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function paymentFor(?User $user = null, ?Listing $listing = null, ?Package $package = null): Payment
{
    $user ??= User::factory()->create(['name' => 'أحمد رمضان', 'phone' => '+201012345678']);
    $listing ??= Listing::factory()->create(['user_id' => $user->id]);
    $package ??= Package::create(['name' => '7 أيام', 'days' => 7, 'price' => 60]);

    return Payment::create([
        'user_id' => $user->id, 'listing_id' => $listing->id, 'package_id' => $package->id,
        'gateway' => 'paymob', 'amount' => $package->price, 'currency' => 'EGP',
    ]);
}

function fakePaymobChain(): void
{
    Http::fake([
        'accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'AUTH_TOKEN']),
        'accept.paymob.com/api/ecommerce/orders' => Http::response(['id' => 555]),
        'accept.paymob.com/api/acceptance/payment_keys' => Http::response(['token' => 'PAY_KEY']),
    ]);
}

// ---------------------------------------------------------------- charge()

it('chains auth, order and payment key requests and returns the iframe URL', function () {
    fakePaymobChain();
    $payment = paymentFor();

    $redirect = (new PaymobGateway('api-key', 'integration-1', 'iframe-9'))->charge($payment);

    expect($redirect->url)->toBe('https://accept.paymob.com/api/acceptance/iframes/iframe-9?payment_token=PAY_KEY')
        ->and($payment->fresh()->gateway_order_id)->toBe('555');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://accept.paymob.com/api/auth/tokens' && $r['api_key'] === 'api-key');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/ecommerce/orders')
        && $r['auth_token'] === 'AUTH_TOKEN'
        && $r['amount_cents'] === 6000
        && $r['currency'] === 'EGP'
        && $r['merchant_order_id'] === "payment-{$payment->id}");
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/payment_keys')
        && $r['order_id'] === 555
        && $r['integration_id'] === 'integration-1'
        && $r['billing_data']['first_name'] === 'أحمد'
        && $r['billing_data']['last_name'] === 'رمضان'
        && $r['billing_data']['phone_number'] === '+201012345678');
});

it('throws when Paymob rejects the auth request', function () {
    Http::fake(['accept.paymob.com/*' => Http::response(['message' => 'invalid api key'], 401)]);

    (new PaymobGateway('bad', 'i', 'f'))->charge(paymentFor());
})->throws(PaymentGatewayException::class, 'invalid api key');

it('throws when the gateway is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    (new PaymobGateway('k', 'i', 'f'))->charge(paymentFor());
})->throws(PaymentGatewayException::class);

it('never leaves an orphan pending payment when the gateway fails', function () {
    Http::fake(['accept.paymob.com/*' => Http::response([], 500)]);
    $user = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $package = Package::create(['name' => '3 أيام', 'days' => 3, 'price' => 30]);
    app()->instance(PaymentGateway::class, new PaymobGateway('k', 'i', 'f'));

    $this->actingAs($user)
        ->post(route('listings.feature.store', $listing), ['package_id' => $package->id])
        ->assertSessionHas('error', __('app.payments.gateway_unavailable'));

    expect(Payment::count())->toBe(0);
});

// ------------------------------------------------------------------ webhook HMAC

function paymobHmacPayload(array $overrides = []): array
{
    // Field values chosen arbitrarily; what matters is that the HMAC is computed over the documented
    // field order (see PaymobWebhookVerifier::FIELDS) with the given secret.
    return array_merge([
        'amount_cents' => 6000, 'created_at' => '2026-01-01T00:00:00.000000', 'currency' => 'EGP',
        'error_occured' => false, 'has_parent_transaction' => false, 'id' => 987654,
        'integration_id' => 111, 'is_3d_secure' => true, 'is_auth' => false, 'is_capture' => false,
        'is_refunded' => false, 'is_standalone_payment' => true, 'is_voided' => false,
        'order' => ['id' => 555], 'owner' => 42, 'pending' => false,
        'source_data' => ['pan' => '1234', 'sub_type' => 'MasterCard', 'type' => 'card'],
        'success' => true,
    ], $overrides);
}

function paymobHmacFor(array $transaction, string $secret): string
{
    $fields = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
        'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
        'source_data.type', 'success'];

    $concatenated = collect($fields)->map(function (string $path) use ($transaction) {
        $value = data_get($transaction, $path);

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    })->implode('');

    return hash_hmac('sha512', $concatenated, $secret);
}

it('accepts a webhook whose HMAC matches and completes the payment', function () {
    config(['services.paymob.hmac_secret' => 'top-secret']);
    $payment = paymentFor();
    $payment->update(['gateway_order_id' => '555']);
    $transaction = paymobHmacPayload();
    $hmac = paymobHmacFor($transaction, 'top-secret');

    $this->postJson(route('payments.webhook.paymob', ['hmac' => $hmac]), ['obj' => $transaction])
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->gateway_transaction_id)->toBe('987654')
        ->and($payment->listing->fresh()->isFeatured())->toBeTrue();
});

it('rejects a webhook with a wrong or missing HMAC and changes nothing', function () {
    config(['services.paymob.hmac_secret' => 'top-secret']);
    $payment = paymentFor();
    $payment->update(['gateway_order_id' => '555']);
    $transaction = paymobHmacPayload();

    $this->postJson(route('payments.webhook.paymob', ['hmac' => 'wrong']), ['obj' => $transaction])->assertStatus(403);
    $this->postJson('/payments/webhook/paymob', ['obj' => $transaction])->assertStatus(403);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('marks the payment failed when Paymob reports success=false, without touching an already-paid one', function () {
    config(['services.paymob.hmac_secret' => 'top-secret']);
    $payment = paymentFor();
    $payment->update(['gateway_order_id' => '555']);
    $transaction = paymobHmacPayload(['success' => false]);
    $hmac = paymobHmacFor($transaction, 'top-secret');

    $this->postJson(route('payments.webhook.paymob', ['hmac' => $hmac]), ['obj' => $transaction])->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);

    // a stray failure callback after the payment already succeeded must not undo it
    app(CompletePayment::class)($payment->fresh());
    $paid = $payment->fresh();
    expect($paid->status)->toBe(PaymentStatus::Paid);

    $hmac2 = paymobHmacFor($transaction, 'top-secret');
    $this->postJson(route('payments.webhook.paymob', ['hmac' => $hmac2]), ['obj' => $transaction])->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('returns 404 for a webhook whose order id matches no payment', function () {
    config(['services.paymob.hmac_secret' => 'top-secret']);
    $transaction = paymobHmacPayload(['order' => ['id' => 999999]]);
    $hmac = paymobHmacFor($transaction, 'top-secret');

    $this->postJson(route('payments.webhook.paymob', ['hmac' => $hmac]), ['obj' => $transaction])->assertStatus(404);
});

it('is idempotent when the webhook is delivered twice', function () {
    config(['services.paymob.hmac_secret' => 'top-secret']);
    $payment = paymentFor();
    $payment->update(['gateway_order_id' => '555']);
    $listing = $payment->listing;
    $listing->update(['featured_until' => null]);
    $transaction = paymobHmacPayload();
    $hmac = paymobHmacFor($transaction, 'top-secret');

    $this->postJson(route('payments.webhook.paymob', ['hmac' => $hmac]), ['obj' => $transaction])->assertOk();
    $firstFeaturedUntil = $listing->fresh()->featured_until;

    $this->postJson(route('payments.webhook.paymob', ['hmac' => $hmac]), ['obj' => $transaction])->assertOk();
    expect($listing->fresh()->featured_until->equalTo($firstFeaturedUntil))->toBeTrue();
});

it('verifies example payloads directly against PaymobWebhookVerifier', function () {
    $verifier = new PaymobWebhookVerifier('secret-value');
    $transaction = paymobHmacPayload();

    expect($verifier->verify($transaction, paymobHmacFor($transaction, 'secret-value')))->toBeTrue()
        ->and($verifier->verify($transaction, paymobHmacFor($transaction, 'other-secret')))->toBeFalse()
        ->and($verifier->verify($transaction, ''))->toBeFalse();

    expect((new PaymobWebhookVerifier(''))->verify($transaction, paymobHmacFor($transaction, '')))->toBeFalse();
});
