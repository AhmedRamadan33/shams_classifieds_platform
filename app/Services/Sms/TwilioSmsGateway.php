<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class TwilioSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $sid,
        private readonly string $token,
        private readonly ?string $from = null,
        private readonly ?string $messagingServiceSid = null,
    ) {}

    public function send(string $phone, string $message): void
    {
        $payload = ['To' => $phone, 'Body' => $message];

        if ($this->messagingServiceSid) {
            $payload['MessagingServiceSid'] = $this->messagingServiceSid;
        } else {
            $payload['From'] = (string) $this->from;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->sid, $this->token)
                ->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", $payload);
        } catch (ConnectionException $e) {
            throw SmsDeliveryException::provider('twilio', $e->getMessage());
        }

        if ($response->failed()) {
            throw SmsDeliveryException::provider('twilio', (string) ($response->json('message') ?? 'HTTP '.$response->status()));
        }
    }
}
