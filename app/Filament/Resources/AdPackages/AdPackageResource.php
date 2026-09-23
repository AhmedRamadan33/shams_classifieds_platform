<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdPackages;

use App\Filament\Concerns\AdminOnlyResource;
use App\Filament\Resources\AdPackages\Pages\CreateAdPackage;
use App\Filament\Resources\AdPackages\Pages\EditAdPackage;
use App\Filament\Resources\AdPackages\Pages\ListAdPackages;
use App\Filament\Resources\AdPackages\Schemas\AdPackageForm;
use App\Filament\Resources\AdPackages\Tables\AdPackagesTable;
use App\Models\AdPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdPackageResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = AdPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return __('app.admin.ad_package');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.ad_packages');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.payments');
    }

    public static function form(Schema $schema): Schema
    {
        return AdPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdPackagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdPackages::route('/'),
            'create' => CreateAdPackage::route('/create'),
            'edit' => EditAdPackage::route('/{record}/edit'),
        ];
    }
}
