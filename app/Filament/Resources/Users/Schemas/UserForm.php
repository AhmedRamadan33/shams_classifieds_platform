<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Rules\PhoneNumber;
use App\Services\PhoneNormalizer;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.auth.name'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label(__('app.auth.phone'))
                    ->inputMode('tel')
                    ->required()
                    ->rules([
                        new PhoneNumber,
                        // Unique after normalization ("010..." and "+2010..." are the same number).
                        fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $normalized = app(PhoneNormalizer::class)->tryNormalize((string) $value);

                            $taken = $normalized !== null && User::query()
                                ->where('phone', $normalized)
                                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($taken) {
                                $fail(__('app.auth.phone_taken'));
                            }
                        },
                    ])
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : (app(PhoneNormalizer::class)->tryNormalize($state) ?? $state))
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('password')
                    ->label(__('app.auth.password'))
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rule(Password::defaults())
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? __('app.admin.password_keep') : null),
                Select::make('roles')
                    ->label(__('app.admin.roles'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Role $record): string => __('app.admin.role_names.'.$record->name))
                    ->multiple()
                    ->preload()
                    ->required(),
                Toggle::make('is_banned')
                    ->label(__('app.admin.is_banned'))
                    ->helperText(__('app.admin.ban_help'))
                    // Nobody can lock themselves out of the panel by mistake.
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
            ]);
    }
}
