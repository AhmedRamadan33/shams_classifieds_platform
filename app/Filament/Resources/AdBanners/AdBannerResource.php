<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdBanners;

use App\Enums\AdBannerStatus;
use App\Filament\Resources\AdBanners\Pages\ListAdBanners;
use App\Filament\Resources\AdBanners\Pages\ViewAdBanner;
use App\Filament\Resources\AdBanners\Schemas\AdBannerInfolist;
use App\Filament\Resources\AdBanners\Tables\AdBannersTable;
use App\Models\AdBanner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AdBannerResource extends Resource
{
    protected static ?string $model = AdBanner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('app.admin.ad_banner');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.ad_banners');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.moderation');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = AdBanner::query()->where('status', AdBannerStatus::Pending->value)->count();

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'adPackage', 'media']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdBannerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdBannersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdBanners::route('/'),
            'view' => ViewAdBanner::route('/{record}'),
        ];
    }
}
