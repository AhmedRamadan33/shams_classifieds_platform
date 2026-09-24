<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Services\PhoneNormalizer;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('app.auth.login_identifier'))
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        if (str_contains((string) $data['phone'], '@')) {
            return [
                'email' => Str::lower(trim((string) $data['phone'])),
                'password' => $data['password'],
            ];
        }

        return [
            'phone' => app(PhoneNormalizer::class)->tryNormalize($data['phone']) ?? $data['phone'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
