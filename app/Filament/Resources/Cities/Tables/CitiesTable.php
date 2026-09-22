<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cities\Tables;

use App\Models\Governorate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('governorate'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.admin.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('governorate.name')
                    ->label(__('app.admin.governorate'))
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('app.admin.slug'))
                    ->searchable()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->sortable(),
            ])
            ->defaultSort('governorate_id')
            ->filters([
                SelectFilter::make('governorate_id')
                    ->label(__('app.admin.governorate'))
                    ->options(fn () => Governorate::query()->ordered()->pluck('name', 'id')->all()),
            ])
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
