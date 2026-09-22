<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use Illuminate\Http\Request;

/**
 * Bot check for public forms (registration and listing creation). Controllers only depend on this
 * interface, so a real provider (Cloudflare Turnstile, reCAPTCHA, hCaptcha) can be plugged in from
 * the service provider without touching them.
 */
interface CaptchaVerifier
{
    /**
     * Whether the request passed the bot check.
     */
    public function verify(Request $request): bool;
}
