<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Enums\OtpResult;
use App\Models\OtpCode;
use App\Services\OtpCooldownException;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->sms = fakeSms();
    $this->otp = app(OtpService::class);
    $this->phone = '+201012345678';
});

it('issues a 6 digit code, sends it by SMS and never stores it in plain text', function () {
    $code = $this->otp->issue($this->phone, OtpPurpose::Register);

    expect($code)->toMatch('/^\d{6}$/')
        ->and($this->sms->lastCode())->toBe($code);

    $stored = OtpCode::sole();

    expect($stored->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $stored->code_hash))->toBeTrue()
        ->and($stored->expires_at->isFuture())->toBeTrue()
        ->and($stored->attempts)->toBe(0);
});

it('enforces a 60 second resend cooldown', function () {
    $this->otp->issue($this->phone, OtpPurpose::Register);

    expect(fn () => $this->otp->issue($this->phone, OtpPurpose::Register))
        ->toThrow(OtpCooldownException::class);

    $this->travel(61)->seconds();

    $this->otp->issue($this->phone, OtpPurpose::Register);

    expect(OtpCode::count())->toBe(2);
});

it('keeps cooldowns separate per phone and purpose', function () {
    $this->otp->issue($this->phone, OtpPurpose::Register);
    $this->otp->issue($this->phone, OtpPurpose::Reset);
    $this->otp->issue('+201099999999', OtpPurpose::Register);

    expect(OtpCode::count())->toBe(3);
});

it('invalidates older codes when a new one is issued', function () {
    $old = $this->otp->issue($this->phone, OtpPurpose::Register);
    $this->travel(61)->seconds();
    $new = $this->otp->issue($this->phone, OtpPurpose::Register);

    expect($this->otp->verify($this->phone, OtpPurpose::Register, $old))->not->toBe(OtpResult::Valid)
        ->and($this->otp->verify($this->phone, OtpPurpose::Register, $new))->toBe(OtpResult::Valid);
});

it('accepts the right code once and consumes it', function () {
    $code = $this->otp->issue($this->phone, OtpPurpose::Register);

    expect($this->otp->verify($this->phone, OtpPurpose::Register, $code))->toBe(OtpResult::Valid)
        ->and(OtpCode::sole()->consumed_at)->not->toBeNull()
        ->and($this->otp->verify($this->phone, OtpPurpose::Register, $code))->toBe(OtpResult::Expired);
});

it('accepts Arabic-Indic digits in the submitted code', function () {
    $code = $this->otp->issue($this->phone, OtpPurpose::Register);
    $arabic = strtr($code, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    expect($this->otp->verify($this->phone, OtpPurpose::Register, $arabic))->toBe(OtpResult::Valid);
});

it('rejects a wrong code and counts the attempt', function () {
    $this->otp->issue($this->phone, OtpPurpose::Register);

    expect($this->otp->verify($this->phone, OtpPurpose::Register, '000000'))->toBe(OtpResult::Invalid)
        ->and(OtpCode::sole()->attempts)->toBe(1);
});

it('locks the code after 5 failed attempts, even for the correct code', function () {
    $code = $this->otp->issue($this->phone, OtpPurpose::Register);
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 4) as $ignored) {
        expect($this->otp->verify($this->phone, OtpPurpose::Register, $wrong))->toBe(OtpResult::Invalid);
    }

    expect($this->otp->verify($this->phone, OtpPurpose::Register, $wrong))->toBe(OtpResult::Locked)
        ->and($this->otp->verify($this->phone, OtpPurpose::Register, $code))->toBe(OtpResult::Locked);
});

it('expires codes after 5 minutes', function () {
    $code = $this->otp->issue($this->phone, OtpPurpose::Register);

    $this->travel(6)->minutes();

    expect($this->otp->verify($this->phone, OtpPurpose::Register, $code))->toBe(OtpResult::Expired);
});

it('does not accept a code issued for another purpose', function () {
    $code = $this->otp->issue($this->phone, OtpPurpose::Register);

    expect($this->otp->verify($this->phone, OtpPurpose::Reset, $code))->toBe(OtpResult::Expired);
});
