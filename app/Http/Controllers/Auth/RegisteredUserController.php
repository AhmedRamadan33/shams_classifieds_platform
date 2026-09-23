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

    public function store(RegisterRequest $request, OtpService $otp): RedirectResponse
    {
        $phone = $request->validated('phone');

        $user = User::firstOrNew(['phone' => $phone]);
        $user->fill([
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
        ])->save();

        try {
            $otp->issue($phone, OtpPurpose::Register);
        } catch (OtpCooldownException) {
        }

        $request->session()->put('verify_phone', $phone);

        return redirect()->route('phone.verification.notice')
            ->with('status', __('app.auth.code_sent'));
    }
}
