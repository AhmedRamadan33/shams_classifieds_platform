<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the value can be normalized to a valid E.164 phone number.
 */
final class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(PhoneNormalizer::class)->tryNormalize($value) === null) {
            $fail('app.auth.phone_invalid')->translate();
        }
    }
}
