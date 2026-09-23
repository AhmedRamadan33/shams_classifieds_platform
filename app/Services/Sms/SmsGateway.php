<?php

declare(strict_types=1);

namespace App\Services\Sms;

interface SmsGateway
{
    public function send(string $phone, string $message): void;
}
