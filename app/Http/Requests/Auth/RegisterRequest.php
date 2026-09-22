<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesPhone;
use App\Rules\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            // An unverified account with the same number (abandoned sign-up) may be registered again.
            'phone' => [
                'required',
                'string',
                new PhoneNumber,
                Rule::unique('users', 'phone')->whereNotNull('phone_verified_at'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.unique' => __('app.auth.phone_taken'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', $this->input('name')))]);
        }
    }
}
