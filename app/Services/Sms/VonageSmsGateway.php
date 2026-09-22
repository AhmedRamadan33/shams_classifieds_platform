<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Vonage (Nexmo) SMS API (https://developer.vonage.com/en/api/sms).
 *
 * Configure with VONAGE_KEY, VONAGE_SECRET and VONAGE_FROM (sender id). Arabic text is sent as
 * unicode so it is not garbled.
 */
final class VonageSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $from,
    ) {}

    public function send(string $phone, string $message): void
    {
        try {
            $response = Http::asForm()->timeout(10)->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $this->key,
                'api_secret' => $this->secret,
                'from' => $this->from,
                'to' => ltrim($phone, '+'),
                'text' => $message,
                'type' => 'unicode',
            ]);
        } catch (ConnectionException $e) {
            throw SmsDeliveryException::provider('vonage', $e->getMessage());
        }

        if ($response->failed()) {
            throw SmsDeliveryException::provider('vonage', 'HTTP '.$response->status());
        }

        // Vonage answers 200 even for rejected messages: status "0" means accepted.
        $status = (string) $response->json('messages.0.status', '0');

        if ($status !== '0') {
            throw SmsDeliveryException::provider('vonage', (string) ($response->json('messages.0.error-text') ?? "status {$status}"));
        }
    }
}
