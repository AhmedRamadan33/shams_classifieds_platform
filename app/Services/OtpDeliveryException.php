<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * The code was created but the SMS could not be sent. The unusable code is removed so the user
 * can retry immediately, and the error is shown as a friendly Arabic message.
 */
final class OtpDeliveryException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The verification SMS could not be delivered.', 0, $previous);
    }

    public function render(Request $request): RedirectResponse|JsonResponse
    {
        $message = __('app.otp.delivery_failed');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return back()->withInput($request->except(['password', 'password_confirmation']))->with('error', $message);
    }
}
