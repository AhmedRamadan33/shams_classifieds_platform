<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

interface WhatsAppGateway
{
    /**
     * @throws WhatsAppDeliveryException
     */
    public function send(string $phone, string $message): void;
}
