<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use Illuminate\Http\Request;

interface CaptchaVerifier
{
    public function verify(Request $request): bool;
}
