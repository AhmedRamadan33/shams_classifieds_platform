<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class PaymobWebhookVerifier
{
    private const FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
        'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
        'source_data.type', 'success',
    ];

    public function __construct(private readonly string $secret) {}

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
