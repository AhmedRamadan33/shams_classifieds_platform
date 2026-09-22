<?php

declare(strict_types=1);

use App\Services\PhoneNormalizer;

it('normalizes local, international and Arabic-digit numbers to the same E.164 value', function (string $input) {
    expect(app(PhoneNormalizer::class)->normalize($input))->toBe('+201012345678');
})->with([
    'local with leading zero' => ['01012345678'],
    'Arabic-Indic digits' => ['٠١٠١٢٣٤٥٦٧٨'],
    'Persian digits' => ['۰۱۰۱۲۳۴۵۶۷۸'],
    'international with plus' => ['+201012345678'],
    'international with 00' => ['00201012345678'],
    'international without plus' => ['201012345678'],
    'national without trunk zero' => ['1012345678'],
    'spaces' => ['010 1234 5678'],
    'dashes' => ['010-1234-5678'],
    'dots and brackets' => ['(010) 1234.5678'],
    'Arabic digits with plus and spaces' => ['+٢٠ ١٠ ١٢٣٤ ٥٦٧٨'],
    'surrounding whitespace' => ['  01012345678  '],
]);

it('rejects numbers with an invalid length or characters', function (string $input) {
    expect(app(PhoneNormalizer::class)->tryNormalize($input))->toBeNull();
})->with([
    'too short' => ['0101234'],
    'too long' => ['010123456789012'],
    'letters' => ['0101234abcd'],
    'empty' => [''],
    'plus in the middle' => ['0101+2345678'],
    'only symbols' => ['---'],
]);

it('throws from normalize() for invalid numbers', function () {
    app(PhoneNormalizer::class)->normalize('123');
})->throws(InvalidArgumentException::class);

it('returns null for a null input', function () {
    expect(app(PhoneNormalizer::class)->tryNormalize(null))->toBeNull();
});

it('uses the configured default country code', function () {
    config(['classifieds.phone_country_code' => '+966']);

    expect(app(PhoneNormalizer::class)->normalize('0512345678'))->toBe('+966512345678');
});

it('keeps numbers from other countries when written in international format', function () {
    expect(app(PhoneNormalizer::class)->normalize('+447911123456'))->toBe('+447911123456');
});
