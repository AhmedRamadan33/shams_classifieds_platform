<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlides\Tables;

use App\Models\HeroSlide;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HeroSlidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('app.admin.image'))
                    ->getStateUsing(fn (HeroSlide $record): ?string => $record->imageUrl())
                    ->imageHeight(48)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),
                TextColumn::make('title')
                    ->label(__('app.admin.title'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('link_url')
                    ->label(__('app.admin.link_url'))
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('app.admin.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
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
