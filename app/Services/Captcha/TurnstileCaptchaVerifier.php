<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile: the widget (see the x-captcha component) adds a `cf-turnstile-response`
 * token to the form; it is checked against Cloudflare's siteverify endpoint.
 *
 * A missing or rejected token always fails. If Cloudflare itself cannot be reached, $failOpen decides:
 * true (default) lets the request through, because the honeypot and the rate limiters still apply and
 * a Cloudflare outage should not stop every registration; false rejects it.
 */
final class TurnstileCaptchaVerifier implements CaptchaVerifier
{
    public const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const FIELD = 'cf-turnstile-response';

    public function __construct(
        private readonly string $secret,
        private readonly bool $failOpen = true,
    ) {}

    public function verify(Request $request): bool
    {
        $token = $request->input(self::FIELD);

        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINT, [
                'secret' => $this->secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException $e) {
            report($e);

            return $this->failOpen;
        }

        if ($response->serverError()) {
            return $this->failOpen;
        }

        return $response->json('success') === true;
    }
}
