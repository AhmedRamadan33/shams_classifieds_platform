<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Default driver while no WhatsApp provider is chosen (or for local development): writes the
 * message to the application log instead of sending it.
 */
final class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info('WhatsApp to '.$phone.': '.$message);
    }
}
