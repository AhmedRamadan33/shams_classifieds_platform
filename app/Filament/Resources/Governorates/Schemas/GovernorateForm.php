<?php

declare(strict_types=1);

namespace App\Filament\Resources\Governorates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GovernorateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.admin.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label(__('app.admin.slug'))
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
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
