<?php

declare(strict_types=1);

namespace App\Filament\Resources\Stores\Tables;

use App\Filament\Support\RowActions;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.admin.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('app.admin.slug'))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(__('app.admin.owner'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('app.admin.is_active'))
                    ->boolean()
                    ->state(fn (Store $record): bool => $record->isActive()),
                TextColumn::make('created_at')
                    ->label(__('app.admin.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                RowActions::group([
                    Action::make('open_public')
                        ->label(__('app.admin.open_public'))
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray')
                        ->url(fn (Store $record): string => $record->url(), shouldOpenInNewTab: true),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
