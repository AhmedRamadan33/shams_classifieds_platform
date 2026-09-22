<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Verifies a Paymob "transaction processed" webhook using its documented HMAC-SHA512 scheme: a fixed,
 * ordered concatenation of fields from the transaction object, hashed with the integration's HMAC
 * secret and compared (constant time) against the `hmac` query parameter Paymob sends.
 *
 * @see https://docs.paymob.com/docs/transaction-callbacks (field order for the transaction callback)
 */
final class PaymobWebhookVerifier
{
    /** Field order Paymob's docs specify for the transaction processed callback, dot paths into the payload. */
    private const FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
        'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
        'source_data.type', 'success',
    ];

    public function __construct(private readonly string $secret) {}

    /**
     * @param  array<string, mixed>  $transaction  the "obj" payload of the webhook
     */
    public function verify(array $transaction, string $providedHmac): bool
    {
        if ($this->secret === '' || $providedHmac === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $this->concatenatedFields($transaction), $this->secret);

        return hash_equals($expected, strtolower($providedHmac));
    }

    private function concatenatedFields(array $transaction): string
    {
        return collect(self::FIELDS)
            ->map(fn (string $path) => $this->stringify(data_get($transaction, $path)))
            ->implode('');
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
