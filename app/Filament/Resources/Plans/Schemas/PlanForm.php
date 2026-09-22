<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.admin.name'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('price')
                    ->label(__('app.admin.price'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn () => config('classifieds.currency_label')),
                TextInput::make('duration_days')
                    ->label(__('app.admin.duration_days'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3650),
                TextInput::make('daily_listing_limit')
                    ->label(__('app.admin.daily_listing_limit'))
                    ->helperText(__('app.admin.daily_listing_limit_help'))
                    ->required()
                    ->numeric()
                    ->minValue(1),
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
