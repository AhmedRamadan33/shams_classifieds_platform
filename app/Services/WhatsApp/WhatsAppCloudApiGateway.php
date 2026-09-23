<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class WhatsAppCloudApiGateway implements WhatsAppGateway
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly string $apiVersion = 'v21.0',
    ) {}

    public function send(string $phone, string $message): void
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        try {
            $response = Http::withToken($this->accessToken)->asJson()->timeout(10)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($phone, '+'),
                'type' => 'text',
                'text' => ['body' => $message, 'preview_url' => false],
            ]);
        } catch (ConnectionException $e) {
            throw WhatsAppDeliveryException::provider('whatsapp', $e->getMessage());
        }

        if ($response->failed()) {
            throw WhatsAppDeliveryException::provider('whatsapp', (string) ($response->json('error.message') ?? $response->body()));
        }
    }
}
