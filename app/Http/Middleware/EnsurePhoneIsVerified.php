<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasVerifiedPhone()) {
            $request->session()->put('verify_phone', $user->phone);
            Auth::guard('web')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('phone.verification.notice');
        }

        return $next($request);
    }
}
