<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('governorate_id')
                    ->label(__('app.admin.governorate'))
                    ->relationship('governorate', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                TextInput::make('name')
                    ->label(__('app.admin.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label(__('app.admin.slug'))
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('governorate_id', $get('governorate_id')),
                    )
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->validationMessages(['regex' => __('app.admin.slug_invalid')])
                    ->helperText(__('app.admin.slug_help')),
                TextInput::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }
}
