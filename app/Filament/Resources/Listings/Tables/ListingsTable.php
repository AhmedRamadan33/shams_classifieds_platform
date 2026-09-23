<?php

declare(strict_types=1);

namespace App\Filament\Resources\Listings\Tables;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\Actions\ListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Category;
use App\Models\Listing;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')
                    ->label(__('app.admin.image'))
                    ->getStateUsing(fn (Listing $record): ?string => ($url = $record->coverUrl('thumb')) ? url($url) : null)
                    ->imageHeight(48)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),
                TextColumn::make('title')
                    ->label(__('app.admin.title'))
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (Listing $record): string => $record->title)
                    ->url(fn (Listing $record): string => ListingResource::getUrl('view', ['record' => $record])),
                TextColumn::make('category.name')
                    ->label(__('app.admin.category'))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('app.admin.owner'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (ListingStatus $state): string => $state->label())
                    ->color(fn (ListingStatus $state): string => $state->filamentColor())
                    ->sortable(),
                IconColumn::make('featured')
                    ->label(__('app.admin.featured'))
                    ->getStateUsing(fn (Listing $record): bool => $record->isFeatured())
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),
                TextColumn::make('created_at')
                    ->label(__('app.admin.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.admin.status'))
                    ->options(ListingStatus::options())
                    ->default(ListingStatus::Pending->value),
                SelectFilter::make('category_id')
                    ->label(__('app.admin.category'))
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TernaryFilter::make('featured')
                    ->label(__('app.admin.featured'))
                    ->queries(
                        true: fn (Builder $query) => $query->featured(),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('featured_until')->orWhere('featured_until', '<=', now())),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                ListingActions::approve(),
                ListingActions::reject(),
                ListingActions::feature(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ListingActions::bulkApprove(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
