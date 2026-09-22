<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Enums\OtpResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpCodeRequest;
use App\Http\Requests\Auth\PhoneRequest;
use App\Models\User;
use App\Services\OtpCooldownException;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Steps 1 and 2 of the phone based password reset: request a code, then verify it.
 * (Step 3, choosing the new password, is NewPasswordController.)
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * The response is identical whether or not the number is registered, so the form cannot be
     * used to discover which numbers have accounts.
     */
    public function store(PhoneRequest $request, OtpService $otp): RedirectResponse
    {
        $phone = $request->validated('phone');

        $user = User::where('phone', $phone)->first();

        if ($user !== null && ! $user->is_banned && $user->hasVerifiedPhone()) {
            try {
                $otp->issue($phone, OtpPurpose::Reset);
            } catch (OtpCooldownException) {
                // Silently reuse the code that was just sent.
            }
        }

        $request->session()->put('reset_phone', $phone);

        return redirect()->route('password.verify')->with('status', __('app.auth.forgot_sent'));
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get('reset_phone');

        if ($phone === null) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-code', ['phone' => $phone]);
    }

    public function verify(OtpCodeRequest $request, OtpService $otp): RedirectResponse
    {
        $phone = $request->session()->get('reset_phone');

        if ($phone === null) {
            return redirect()->route('password.request');
        }

        $result = $otp->verify($phone, OtpPurpose::Reset, $request->validated('code'));

        if ($result !== OtpResult::Valid) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        // The code is consumed; this short-lived flag lets the user choose a new password.
        $request->session()->put('reset_verified', ['phone' => $phone, 'at' => now()->timestamp]);

        return redirect()->route('password.reset');
    }

    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $phone = $request->session()->get('reset_phone');

        if ($phone === null) {
            return redirect()->route('password.request');
        }

        $user = User::where('phone', $phone)->first();

        if ($user !== null && ! $user->is_banned && $user->hasVerifiedPhone()) {
            try {
                $otp->issue($phone, OtpPurpose::Reset);
            } catch (OtpCooldownException $e) {
                return back()->withErrors(['code' => __('app.auth.cooldown', ['seconds' => $e->secondsRemaining])]);
            }
        }

        return back()->with('status', __('app.auth.code_sent'));
    }
}
