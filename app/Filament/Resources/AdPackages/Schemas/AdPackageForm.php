<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdPackages\Schemas;

use App\Enums\AdPlacement;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.admin.name'))
                    ->required()
                    ->maxLength(100),
                Select::make('placement')
                    ->label(__('app.admin.placement'))
                    ->options(AdPlacement::options())
                    ->required(),
                TextInput::make('duration_days')
                    ->label(__('app.admin.days'))
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
