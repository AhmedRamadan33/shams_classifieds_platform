<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Step 3 of the password reset: choose the new password after the OTP was verified.
 */
class NewPasswordController extends Controller
{
    /** How long (minutes) a verified code allows choosing a new password. */
    private const WINDOW_MINUTES = 10;

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->verifiedPhone($request) === null) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $phone = $this->verifiedPhone($request);

        if ($phone === null) {
            return redirect()->route('password.request')->with('error', __('app.auth.reset_expired'));
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::where('phone', $phone)->first();

        if ($user === null || $user->is_banned) {
            return redirect()->route('password.request');
        }

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        $request->session()->forget(['reset_phone', 'reset_verified']);

        return redirect()->route('login')->with('status', __('app.auth.password_reset_done'));
    }

    private function verifiedPhone(Request $request): ?string
    {
        $data = $request->session()->get('reset_verified');

        if (! is_array($data) || ! isset($data['phone'], $data['at'])) {
            return null;
        }

        if (now()->timestamp - (int) $data['at'] > self::WINDOW_MINUTES * 60) {
            $request->session()->forget('reset_verified');

            return null;
        }

        return (string) $data['phone'];
    }
}
