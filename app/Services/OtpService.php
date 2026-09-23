<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Enums\OtpResult;
use App\Models\OtpCode;
use App\Services\Sms\SmsDeliveryException;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class OtpService
{
    public function __construct(private readonly SmsGateway $sms) {}

    public function issue(string $phone, OtpPurpose $purpose): string
    {
        $cooldown = (int) config('classifieds.otp.resend_cooldown_seconds');

        $latest = OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose->value)
            ->latest('id')
            ->first();

        if ($latest !== null && $cooldown > 0) {
            $elapsed = (int) $latest->created_at->diffInSeconds(now(), true);

            if ($elapsed < $cooldown) {
                throw new OtpCooldownException($cooldown - $elapsed);
            }
        }

        $length = (int) config('classifieds.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('classifieds.otp.ttl_minutes');

        $otp = DB::transaction(function () use ($phone, $purpose, $code, $ttl): OtpCode {
            OtpCode::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose->value)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return OtpCode::create([
                'phone' => $phone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($ttl),
            ]);
        });

        try {
            $this->sms->send($phone, __('app.otp.message', [
                'brand' => __('app.brand'),
                'code' => $code,
                'minutes' => $ttl,
            ]));
        } catch (SmsDeliveryException $e) {
            report($e);

            $otp->delete();

            throw new OtpDeliveryException($e);
        }

        return $code;
    }

    public function verify(string $phone, OtpPurpose $purpose, string $code): OtpResult
    {
        $code = ArabicText::toLatinDigits(trim($code));
        $maxAttempts = (int) config('classifieds.otp.max_attempts');

        return DB::transaction(function () use ($phone, $purpose, $code, $maxAttempts): OtpResult {
            $otp = OtpCode::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose->value)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->expires_at->isPast()) {
                return OtpResult::Expired;
            }

            if ($otp->attempts >= $maxAttempts) {
                return OtpResult::Locked;
            }

            $otp->increment('attempts');

            if (! Hash::check($code, $otp->code_hash)) {
                return $otp->attempts >= $maxAttempts ? OtpResult::Locked : OtpResult::Invalid;
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return OtpResult::Valid;
        });
    }
}
