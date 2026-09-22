<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\SubscriptionStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('app.admin.owner'))
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label(__('app.admin.plan')),
                TextColumn::make('status')
                    ->label(__('app.admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => $state->filamentColor()),
                TextColumn::make('starts_at')
                    ->label(__('app.admin.starts_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('app.admin.expires_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('app.admin.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.admin.status'))
                    ->options(SubscriptionStatus::class),
            ]);
    }
}
