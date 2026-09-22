<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Default driver while no SMS provider is chosen: the message (including the OTP) is written
 * to the application log so it can be read locally from storage/logs.
 */
final class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS to '.$phone.': '.$message);
    }
}
