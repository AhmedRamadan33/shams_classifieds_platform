<?php

declare(strict_types=1);

namespace App\Filament\Resources\Governorates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GovernoratesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('cities'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.admin.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('app.admin.slug'))
                    ->searchable()
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('cities_count')
                    ->label(__('app.admin.cities'))
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
