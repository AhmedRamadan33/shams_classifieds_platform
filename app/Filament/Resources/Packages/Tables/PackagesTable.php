<?php

declare(strict_types=1);

namespace App\Filament\Resources\Packages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.admin.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('days')
                    ->label(__('app.admin.days'))
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('app.admin.price'))
                    ->money(fn () => config('classifieds.currency_code'))
                    ->sortable(),
                TextColumn::make('payments_count')
                    ->label(__('app.admin.payments'))
                    ->counts('payments')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('app.admin.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('app.admin.is_active')),
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
