<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Captcha\CaptchaVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spam protection for public forms. Real users never see the hidden honeypot input, so a filled
 * value means a bot: the request is rejected. The CAPTCHA verifier (a no-op by default) runs too.
 */
class RejectHoneypot
{
    public function __construct(private readonly CaptchaVerifier $captcha) {}

    public function handle(Request $request, Closure $next): Response
    {
        $field = (string) config('classifieds.honeypot_field');

        if ($field !== '' && $request->filled($field)) {
            return $this->reject($request);
        }

        if (! $this->captcha->verify($request)) {
            return $this->reject($request);
        }

        return $next($request);
    }

    private function reject(Request $request): Response
    {
        $message = __('app.security.spam_rejected');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withInput($request->except(['password', 'password_confirmation']))->with('error', $message);
    }
}
