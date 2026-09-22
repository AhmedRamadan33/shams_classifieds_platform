<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Meta's WhatsApp Cloud API (graph.facebook.com), sending a free-form text message.
 *
 * Note (real-world limitation, not something code can work around): Meta only allows free-form text
 * outside an approved message template within the 24-hour window after the customer last messaged
 * the business number. A notification like "your listing was approved" sent to a customer who never
 * initiated a WhatsApp conversation will usually need a pre-approved template message instead — set
 * that up in the Meta Business dashboard for production use; this gateway sends whichever $message it
 * is given either way.
 */
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
