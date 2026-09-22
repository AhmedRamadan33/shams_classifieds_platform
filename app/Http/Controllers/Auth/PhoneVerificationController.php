<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Enums\OtpResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpCodeRequest;
use App\Models\User;
use App\Services\OtpCooldownException;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Confirms a phone number with the OTP sent at registration (or at login for unverified accounts).
 */
class PhoneVerificationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasVerifiedPhone()) {
            return redirect()->route('dashboard');
        }

        $phone = $this->phone($request);

        if ($phone === null) {
            return redirect()->route('login');
        }

        return view('auth.verify-phone', ['phone' => $phone]);
    }

    public function store(OtpCodeRequest $request, OtpService $otp): RedirectResponse
    {
        $phone = $this->phone($request);

        if ($phone === null) {
            return redirect()->route('login');
        }

        $result = $otp->verify($phone, OtpPurpose::Register, $request->validated('code'));

        if ($result !== OtpResult::Valid) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        $user = User::where('phone', $phone)->first();

        if ($user === null) {
            return redirect()->route('register');
        }

        if ($user->is_banned) {
            throw ValidationException::withMessages(['code' => __('app.auth.banned')]);
        }

        $user->markPhoneAsVerified();

        Auth::guard('web')->login($user);

        $request->session()->forget('verify_phone');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('success', __('app.auth.verified'));
    }

    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $phone = $this->phone($request);

        if ($phone === null) {
            return redirect()->route('login');
        }

        try {
            $otp->issue($phone, OtpPurpose::Register);
        } catch (OtpCooldownException $e) {
            return back()->withErrors(['code' => __('app.auth.cooldown', ['seconds' => $e->secondsRemaining])]);
        }

        return back()->with('status', __('app.auth.code_sent'));
    }

    private function phone(Request $request): ?string
    {
        return $request->session()->get('verify_phone') ?? $request->user()?->phone;
    }
}
