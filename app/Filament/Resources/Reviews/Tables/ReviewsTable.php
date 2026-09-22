<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Tables;

use App\Filament\Resources\Reviews\Actions\ReviewActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('seller.name')
                    ->label(__('app.admin.owner'))
                    ->searchable(),
                TextColumn::make('reviewer.name')
                    ->label(__('app.admin.reviewer'))
                    ->searchable(),
                TextColumn::make('rating')
                    ->label(__('app.admin.rating'))
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->sortable(),
                TextColumn::make('comment')
                    ->label(__('app.admin.comment'))
                    ->limit(60)
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->comment),
                IconColumn::make('is_hidden')
                    ->label(__('app.admin.is_hidden'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('app.admin.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_hidden')
                    ->label(__('app.admin.is_hidden')),
            ])
            ->recordActions([
                ReviewActions::hide(),
                ReviewActions::unhide(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
