<?php

declare(strict_types=1);

namespace App\Services\Sms;

interface SmsGateway
{
    /**
     * Send a text message to an E.164 phone number.
     */
    public function send(string $phone, string $message): void;
}
