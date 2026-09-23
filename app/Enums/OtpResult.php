<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpResult: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Locked = 'locked';

    public function message(): ?string
    {
        return match ($this) {
            self::Valid => null,
            self::Invalid => __('app.auth.code_invalid'),
            self::Expired => __('app.auth.code_expired'),
            self::Locked => __('app.auth.code_locked'),
        };
    }
}
