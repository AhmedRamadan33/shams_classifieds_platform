<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\OtpCooldownException;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Create an unverified account and send the OTP. The user is only logged in once the
     * code has been confirmed on the verification page.
     */
    public function store(RegisterRequest $request, OtpService $otp): RedirectResponse
    {
        $phone = $request->validated('phone');

        // An abandoned, still unverified sign-up with the same number is simply re-used.
        $user = User::firstOrNew(['phone' => $phone]);
        $user->fill([
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
        ])->save();

        try {
            $otp->issue($phone, OtpPurpose::Register);
        } catch (OtpCooldownException) {
            // A code was sent moments ago; the verification page offers a resend after the cooldown.
        }

        $request->session()->put('verify_phone', $phone);

        return redirect()->route('phone.verification.notice')
            ->with('status', __('app.auth.code_sent'));
    }
}
