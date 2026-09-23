<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesPhone;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class PhoneRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

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
