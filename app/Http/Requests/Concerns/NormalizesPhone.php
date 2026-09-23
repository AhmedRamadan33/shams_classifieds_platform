<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\PhoneNormalizer;

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
