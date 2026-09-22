<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\PhoneNormalizer;

/**
 * Replaces the submitted phone with its E.164 form before validation. When it cannot be
 * normalized the raw value is kept so the PhoneNumber rule reports it.
 */
trait NormalizesPhone
{
    protected function normalizePhoneInput(string $key = 'phone'): void
    {
        $raw = $this->input($key);

        if (is_string($raw)) {
            $this->merge([$key => app(PhoneNormalizer::class)->tryNormalize($raw) ?? $raw]);
        }
    }
}
