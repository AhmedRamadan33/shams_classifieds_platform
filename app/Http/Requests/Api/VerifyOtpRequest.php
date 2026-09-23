<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\NormalizesPhone;
use App\Rules\PhoneNumber;
use App\Services\ArabicText;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $length = (int) config('classifieds.otp.length');

        return [
            'phone' => ['required', 'string', new PhoneNumber],
            'code' => ['required', 'string', 'regex:/^\d{'.$length.'}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => __('app.auth.code_format', ['length' => config('classifieds.otp.length')]),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();

        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', ArabicText::toLatinDigits($this->input('code')))]);
        }
    }
}
