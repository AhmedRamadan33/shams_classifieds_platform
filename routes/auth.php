<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware(['honeypot', 'throttle:otp']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    // Password reset by phone: request code -> verify code -> new password.
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:otp')->name('password.email');

    Route::get('forgot-password/verify', [PasswordResetLinkController::class, 'showVerify'])->name('password.verify');
    Route::post('forgot-password/verify', [PasswordResetLinkController::class, 'verify'])->middleware('throttle:otp')->name('password.verify.store');
    Route::post('forgot-password/resend', [PasswordResetLinkController::class, 'resend'])->middleware('throttle:otp')->name('password.resend');

    Route::get('reset-password', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Phone verification is reachable by guests (right after registering/logging in) and by
// signed-in users whose number is still unverified.
Route::get('verify-phone', [PhoneVerificationController::class, 'create'])->name('phone.verification.notice');
Route::post('verify-phone', [PhoneVerificationController::class, 'store'])->middleware('throttle:otp')->name('phone.verification.verify');
Route::post('verify-phone/resend', [PhoneVerificationController::class, 'resend'])->middleware('throttle:otp')->name('phone.verification.resend');

Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
