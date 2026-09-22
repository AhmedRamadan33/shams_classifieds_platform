<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Tables;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Reports\Actions\ReportActions;
use App\Models\Report;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('listing.title')
                    ->label(__('app.admin.listing'))
                    ->limit(45)
                    ->searchable()
                    ->url(fn (Report $record): ?string => $record->listing !== null && ! $record->listing->trashed()
                        ? ListingResource::getUrl('view', ['record' => $record->listing])
                        : null),
                TextColumn::make('reporter.name')
                    ->label(__('app.admin.reporter'))
                    ->searchable(),
                TextColumn::make('reason')
                    ->label(__('app.report.reason'))
                    ->badge()
                    ->formatStateUsing(fn (ReportReason $state): string => $state->label()),
                TextColumn::make('note')
                    ->label(__('app.report.note'))
                    ->limit(60)
                    ->placeholder('—')
                    ->tooltip(fn (Report $record): ?string => $record->note),
                TextColumn::make('status')
                    ->label(__('app.admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state): string => $state->label())
                    ->color(fn (ReportStatus $state): string => $state->filamentColor()),
                TextColumn::make('handler.name')
                    ->label(__('app.admin.handled_by'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('app.admin.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.admin.status'))
                    ->options(ReportStatus::options())
                    ->default(ReportStatus::Open->value),
                SelectFilter::make('reason')
                    ->label(__('app.report.reason'))
                    ->options(ReportReason::options()),
            ])
            ->recordActions([
                ReportActions::resolve(),
                ReportActions::dismiss(),
                ReportActions::removeListing(),
            ]);
    }
}
