<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('app.admin.title'))
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
                    ->helperText(__('app.admin.page_slug_help')),
                Textarea::make('body')
                    ->label(__('app.admin.body'))
                    ->required()
                    ->rows(16)
                    ->helperText(__('app.admin.body_help'))
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Toggle::make('is_published')
                    ->label(__('app.admin.is_published'))
                    ->default(true),
            ]);
    }
}
