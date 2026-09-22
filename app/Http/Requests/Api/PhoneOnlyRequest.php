<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\NormalizesPhone;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Used where only a phone number is needed (resending an OTP).
 */
class PhoneOnlyRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new PhoneNumber],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();
    }
}
