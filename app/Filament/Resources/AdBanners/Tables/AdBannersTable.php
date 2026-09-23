<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdBanners\Tables;

use App\Enums\AdBannerStatus;
use App\Enums\AdPlacement;
use App\Filament\Resources\AdBanners\Actions\AdBannerActions;
use App\Models\AdBanner;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdBannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('app.admin.image'))
                    ->getStateUsing(fn (AdBanner $record): ?string => $record->imageUrl())
                    ->imageHeight(40)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),
                TextColumn::make('user.name')
                    ->label(__('app.admin.advertiser'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('placement')
                    ->label(__('app.admin.placement'))
                    ->formatStateUsing(fn (AdPlacement $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('destination')
                    ->label(__('app.admin.destination'))
                    ->getStateUsing(fn (AdBanner $record): string => $record->destinationLabel())
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('app.admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (AdBannerStatus $state): string => $state->label())
                    ->color(fn (AdBannerStatus $state): string => $state->filamentColor())
                    ->sortable(),
                TextColumn::make('clicks')
                    ->label(__('app.admin.clicks'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('app.admin.expires_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.admin.status'))
                    ->options(collect(AdBannerStatus::cases())->mapWithKeys(fn (AdBannerStatus $s) => [$s->value => $s->label()])->all()),
                SelectFilter::make('placement')
                    ->label(__('app.admin.placement'))
                    ->options(AdPlacement::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                AdBannerActions::approve(),
                AdBannerActions::reject(),
            ]);
    }
}
