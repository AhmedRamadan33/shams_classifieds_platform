<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\ArabicText;
use Illuminate\Foundation\Http\FormRequest;

class OtpCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $length = (int) config('classifieds.otp.length');

        return [
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
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', ArabicText::toLatinDigits($this->input('code')))]);
        }
    }
}
