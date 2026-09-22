<?php

declare(strict_types=1);

namespace App\Filament\Resources\Listings;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Filament\Resources\Listings\Schemas\ListingInfolist;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\Listing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The moderation queue. Visible to admins and moderators (ListingPolicy grants staff everything);
 * listings are created by users on the public site, never here.
 */
class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('app.admin.listing');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.listings');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.moderation');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Listing::query()->where('status', ListingStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Everything the table and the view page render is loaded up front (lazy loading is disabled).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'category', 'governorate', 'city', 'media', 'fieldValues.field']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ListingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListings::route('/'),
            'view' => ViewListing::route('/{record}'),
        ];
    }
}
