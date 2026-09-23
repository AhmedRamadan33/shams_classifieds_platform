<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OtpPurpose;
use App\Enums\OtpResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PhoneOnlyRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\OtpCooldownException;
use App\Services\OtpDeliveryException;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, OtpService $otp): JsonResponse
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
        } catch (OtpDeliveryException) {
            return response()->json(['message' => __('app.otp.delivery_failed')], 503);
        }

        return response()->json(['message' => __('app.auth.code_sent'), 'phone' => $phone], 201);
    }

    public function resendOtp(PhoneOnlyRequest $request, OtpService $otp): JsonResponse
    {
        try {
            $otp->issue($request->validated('phone'), OtpPurpose::Register);
        } catch (OtpCooldownException $e) {
            return response()->json(['message' => __('app.auth.cooldown', ['seconds' => $e->secondsRemaining])], 429);
        } catch (OtpDeliveryException) {
            return response()->json(['message' => __('app.otp.delivery_failed')], 503);
        }

        return response()->json(['message' => __('app.auth.code_sent')]);
    }

    public function verifyOtp(VerifyOtpRequest $request, OtpService $otp): JsonResponse
    {
        $phone = $request->validated('phone');
        $result = $otp->verify($phone, OtpPurpose::Register, $request->validated('code'));

        if ($result !== OtpResult::Valid) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        $user = User::where('phone', $phone)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['phone' => __('app.auth.banned')]);
        }

        if ($user->is_banned) {
            throw ValidationException::withMessages(['code' => __('app.auth.banned')]);
        }

        $user->markPhoneAsVerified();

        return $this->tokenResponse($user, $request);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->retrieveUser();

        if ($user->is_banned) {
            throw ValidationException::withMessages(['phone' => __('app.auth.banned')]);
        }

        if (! $user->hasVerifiedPhone()) {
            return response()->json([
                'message' => __('app.auth.verify_first'),
                'phone_verified' => false,
                'phone' => $user->phone,
            ], 403);
        }

        return $this->tokenResponse($user, $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('app.nav.logout')]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    private function tokenResponse(User $user, Request $request): JsonResponse
    {
        $deviceName = (string) ($request->input('device_name') ?? $request->userAgent() ?? 'api');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'data' => new UserResource($user),
        ]);
    }
}
