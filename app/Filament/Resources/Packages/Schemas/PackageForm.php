<?php

declare(strict_types=1);

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.admin.name'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('days')
                    ->label(__('app.admin.days'))
                    ->helperText(__('app.admin.days_help'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365),
                TextInput::make('price')
                    ->label(__('app.admin.price'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn () => config('classifieds.currency_label')),
                TextInput::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('app.admin.is_active'))
                    ->default(true),
            ]);
    }
}
