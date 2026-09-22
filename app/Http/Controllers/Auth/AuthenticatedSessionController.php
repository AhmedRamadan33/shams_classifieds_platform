<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\OtpCooldownException;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, OtpService $otp): RedirectResponse
    {
        $user = $request->retrieveUser();

        if ($user->is_banned) {
            throw ValidationException::withMessages(['phone' => __('app.auth.banned')]);
        }

        // Unverified accounts are sent to the OTP page instead of getting a session.
        if (! $user->hasVerifiedPhone()) {
            try {
                $otp->issue($user->phone, OtpPurpose::Register);
            } catch (OtpCooldownException) {
                // Reuse the code that was just sent.
            }

            $request->session()->put('verify_phone', $user->phone);

            return redirect()->route('phone.verification.notice')
                ->with('status', __('app.auth.verify_first'));
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
