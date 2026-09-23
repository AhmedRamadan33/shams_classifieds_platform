<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

final class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info('WhatsApp to '.$phone.': '.$message);
    }
}
