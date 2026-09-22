<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Tables;

use App\Models\Page;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('app.admin.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('app.admin.slug'))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->url(fn (Page $record): string => route('pages.show', $record->slug), shouldOpenInNewTab: true),
                IconColumn::make('is_published')
                    ->label(__('app.admin.is_published'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('app.admin.updated_at'))
                    ->since()
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
