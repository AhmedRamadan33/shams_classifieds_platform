<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\ArabicText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The 6-digit code typed on the verification pages.
 */
class OtpCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $length = (int) config('classifieds.otp.length');

        return [
            'code' => ['required', 'string', 'regex:/^\d{'.$length.'}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('app.auth.code_format', ['length' => config('classifieds.otp.length')]),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', ArabicText::toLatinDigits($this->input('code')))]);
        }
    }
}
