<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('app.admin.owner'))
                    ->searchable(),
                TextColumn::make('listing.title')
                    ->label(__('app.admin.listing'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label(__('app.admin.package')),
                TextColumn::make('amount')
                    ->label(__('app.admin.amount'))
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                TextColumn::make('gateway')
                    ->label(__('app.admin.gateway')),
                TextColumn::make('status')
                    ->label(__('app.admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->filamentColor()),
                TextColumn::make('paid_at')
                    ->label(__('app.admin.paid_at'))
                    ->since()
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
                    ->options(PaymentStatus::class),
                SelectFilter::make('gateway')
                    ->label(__('app.admin.gateway'))
                    ->options(fn () => Payment::query()->distinct()->pluck('gateway', 'gateway')->all()),
            ]);
    }
}
