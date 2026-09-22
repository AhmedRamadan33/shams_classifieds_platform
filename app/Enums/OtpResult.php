<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of checking a submitted one-time code.
 */
enum OtpResult: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Locked = 'locked';

    /**
     * Arabic message for the failure cases (Valid has none).
     */
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
