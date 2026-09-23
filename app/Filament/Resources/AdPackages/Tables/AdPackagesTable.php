<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdPackages\Tables;

use App\Enums\AdPlacement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AdPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.admin.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('placement')
                    ->label(__('app.admin.placement'))
                    ->formatStateUsing(fn (AdPlacement $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->label(__('app.admin.days'))
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('app.admin.price'))
                    ->money(fn () => config('classifieds.currency_code'))
                    ->sortable(),
                TextColumn::make('ad_banners_count')
                    ->label(__('app.admin.ad_banners'))
                    ->counts('adBanners')
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
                SelectFilter::make('placement')
                    ->label(__('app.admin.placement'))
                    ->options(AdPlacement::options()),
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
