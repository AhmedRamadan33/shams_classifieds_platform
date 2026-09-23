<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Captcha\CaptchaVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
