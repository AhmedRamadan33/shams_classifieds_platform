<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use Illuminate\Http\Request;

/**
 * Default: no CAPTCHA provider is configured, every request passes. The honeypot field and the
 * rate limiters still protect the forms.
 */
final class NullCaptchaVerifier implements CaptchaVerifier
{
    public function verify(Request $request): bool
    {
        return true;
    }
}
