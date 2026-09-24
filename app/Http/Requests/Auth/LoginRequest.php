<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesPhone;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->logsInWithEmail()) {
            $this->merge(['phone' => Str::lower(trim((string) $this->input('phone')))]);

            return;
        }

        $this->normalizePhoneInput();
    }

    public function logsInWithEmail(): bool
    {
        return str_contains((string) $this->input('phone'), '@');
    }

    public function retrieveUser(): User
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::guard('web')->getProvider();
        $credentials = [$this->logsInWithEmail() ? 'email' : 'phone' => $this->string('phone')->toString()];

        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, ['password' => (string) $this->input('password')])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'phone' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'phone' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('phone')).'|'.$this->ip());
    }
}
