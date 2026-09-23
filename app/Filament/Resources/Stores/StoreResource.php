<?php

declare(strict_types=1);

namespace App\Filament\Resources\Stores;

use App\Filament\Concerns\AdminOnlyResource;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StoreResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 24;

    public static function getModelLabel(): string
    {
        return __('app.admin.store');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.stores');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.payments');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
        ];
    }
}
