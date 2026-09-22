<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent')->withCount('fields'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.admin.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label(__('app.admin.parent'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('app.admin.slug'))
                    ->searchable()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->toggleable(),
                TextColumn::make('fields_count')
                    ->label(__('app.admin.fields_count'))
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
                SelectFilter::make('parent_id')
                    ->label(__('app.admin.parent'))
                    ->options(fn () => Category::query()->whereNull('parent_id')->orderBy('sort_order')->pluck('name', 'id')->all()),
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
