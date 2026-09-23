<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Sms\SmsGateway;

final class FakeSmsGateway implements SmsGateway
{
    public array $messages = [];

    public function send(string $phone, string $message): void
    {
        $this->messages[] = ['phone' => $phone, 'message' => $message];
    }

    public function lastCode(): ?string
    {
        $last = end($this->messages);

        return $last && preg_match('/\d{6}/', $last['message'], $m) ? $m[0] : null;
    }

    public function count(): int
    {
        return count($this->messages);
    }
}
