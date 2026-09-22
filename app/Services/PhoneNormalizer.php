<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Turns whatever a user types into an E.164 number ("+201012345678").
 *
 * Handles Arabic-Indic digits, spaces/dashes/dots/brackets, local numbers with a leading 0
 * (converted with config('classifieds.phone_country_code')), "00" prefixes and bare national numbers.
 */
final class PhoneNormalizer
{
    /**
     * @throws InvalidArgumentException when the input is not a plausible phone number
     */
    public function normalize(string $input): string
    {
        return $this->tryNormalize($input)
            ?? throw new InvalidArgumentException('Invalid phone number.');
    }

    /**
     * Same as normalize() but returns null instead of throwing.
     */
    public function tryNormalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = trim(ArabicText::toLatinDigits($input));

        // Only digits, an optional leading "+", and common separators are allowed.
        if ($value === '' || ! preg_match('/^\+?[\d\s\-\.\(\)]+$/', $value)) {
            return null;
        }

        $hasPlus = str_starts_with($value, '+');
        $digits = (string) preg_replace('/\D+/', '', $value);
        $countryCode = ltrim((string) config('classifieds.phone_country_code'), '+');

        if ($hasPlus) {
            $international = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $international = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $international = $countryCode.substr($digits, 1);
        } elseif (str_starts_with($digits, $countryCode) && strlen($digits) >= strlen($countryCode) + 8) {
            $international = $digits;
        } else {
            $international = $countryCode.$digits;
        }

        return $this->isValidLength($international, $countryCode) ? '+'.$international : null;
    }

    private function isValidLength(string $international, string $countryCode): bool
    {
        // E.164: no leading zero, at most 15 digits in total.
        if (! preg_match('/^[1-9]\d{7,14}$/', $international)) {
            return false;
        }

        if (str_starts_with($international, $countryCode)) {
            $national = strlen($international) - strlen($countryCode);

            return $national >= (int) config('classifieds.phone_national_min', 8)
                && $national <= (int) config('classifieds.phone_national_max', 10);
        }

        return true;
    }
}
