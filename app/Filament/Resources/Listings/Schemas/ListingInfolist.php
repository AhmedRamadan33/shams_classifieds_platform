<?php

declare(strict_types=1);

namespace App\Filament\Resources\Listings\Schemas;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('app.admin.listing'))
                    ->columns(2)
                    ->columnSpan(2)
                    ->components([
                        TextEntry::make('title')
                            ->label(__('app.admin.title'))
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label(__('app.admin.status'))
                            ->badge()
                            ->formatStateUsing(fn (ListingStatus $state): string => $state->label())
                            ->color(fn (ListingStatus $state): string => $state->filamentColor()),
                        TextEntry::make('rejection_reason')
                            ->label(__('app.admin.rejection_reason'))
                            ->placeholder('—')
                            ->visible(fn (Listing $record): bool => $record->status === ListingStatus::Rejected),
                        TextEntry::make('category.name')->label(__('app.admin.category')),
                        TextEntry::make('governorate.name')
                            ->label(__('app.admin.governorate'))
                            ->formatStateUsing(fn (string $state, Listing $record): string => $state.($record->city ? ' - '.$record->city->name : '')),
                        TextEntry::make('price')
                            ->label(__('app.admin.price'))
                            ->getStateUsing(fn (Listing $record): string => $record->formattedPrice(withType: true)),
                        TextEntry::make('price_type')
                            ->label(__('app.admin.price_type'))
                            ->formatStateUsing(fn (PriceType $state): string => $state->label()),
                        TextEntry::make('phone')
                            ->label(__('app.admin.phone'))
                            ->extraAttributes(['dir' => 'ltr']),
                        TextEntry::make('views')->label(__('app.admin.views'))->numeric(),
                        TextEntry::make('description')
                            ->label(__('app.admin.description'))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                        KeyValueEntry::make('field_values')
                            ->label(__('app.admin.field_values'))
                            ->getStateUsing(fn (Listing $record): array => $record->fieldValues
                                ->mapWithKeys(fn (ListingFieldValue $value) => [$value->field->name => $value->value])
                                ->all())
                            ->keyLabel(__('app.admin.field'))
                            ->valueLabel(__('app.admin.value'))
                            ->columnSpanFull(),
                        ImageEntry::make('images')
                            ->label(__('app.admin.images'))
                            ->getStateUsing(fn (Listing $record): array => $record->getMedia(Listing::IMAGES)
                                ->map(fn (Media $media) => url($media->hasGeneratedConversion('medium') ? $media->getUrl('medium') : $media->getUrl()))
                                ->all())
                            ->imageHeight(160)
                            ->columnSpanFull()
                            ->placeholder(__('app.listing_page.no_images')),
                    ]),

                Section::make(__('app.admin.details'))
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('user.name')->label(__('app.admin.owner')),
                        TextEntry::make('user.phone')
                            ->label(__('app.admin.phone'))
                            ->extraAttributes(['dir' => 'ltr']),
                        TextEntry::make('created_at')->label(__('app.admin.created_at'))->dateTime(),
                        TextEntry::make('published_at')->label(__('app.admin.published_at'))->dateTime()->placeholder('—'),
                        TextEntry::make('expires_at')->label(__('app.admin.expires_at'))->dateTime()->placeholder('—'),
                        TextEntry::make('featured_until')->label(__('app.admin.featured_until'))->dateTime()->placeholder('—'),
                    ]),
            ]);
    }
}
