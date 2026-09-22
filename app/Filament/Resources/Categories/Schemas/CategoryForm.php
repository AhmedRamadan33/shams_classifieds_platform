<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Support\CategoryIcons;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label(__('app.admin.parent'))
                    ->relationship(
                        'parent',
                        'name',
                        // A category can never be its own parent.
                        modifyQueryUsing: fn (Builder $query, ?Category $record) => $record
                            ? $query->whereKeyNot($record->getKey())->orderBy('sort_order')
                            : $query->orderBy('sort_order'),
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder(__('app.admin.no_parent'))
                    ->helperText(__('app.admin.parent_help')),
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
                Select::make('icon')
                    ->label(__('app.admin.icon'))
                    ->options(CategoryIcons::options())
                    ->placeholder(__('app.admin.no_icon')),
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
