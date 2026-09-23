<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use Illuminate\Http\Request;

final class NullCaptchaVerifier implements CaptchaVerifier
{
    public function verify(Request $request): bool
    {
        return true;
    }
}
