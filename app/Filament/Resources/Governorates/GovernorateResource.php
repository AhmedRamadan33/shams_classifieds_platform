<?php

declare(strict_types=1);

namespace App\Filament\Resources\Governorates;

use App\Filament\Concerns\AdminOnlyResource;
use App\Filament\Resources\Governorates\Pages\CreateGovernorate;
use App\Filament\Resources\Governorates\Pages\EditGovernorate;
use App\Filament\Resources\Governorates\Pages\ListGovernorates;
use App\Filament\Resources\Governorates\Schemas\GovernorateForm;
use App\Filament\Resources\Governorates\Tables\GovernoratesTable;
use App\Models\Governorate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GovernorateResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = Governorate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('app.admin.governorate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.governorates');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.catalog');
    }

    public static function form(Schema $schema): Schema
    {
        return GovernorateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GovernoratesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGovernorates::route('/'),
            'create' => CreateGovernorate::route('/create'),
            'edit' => EditGovernorate::route('/{record}/edit'),
        ];
    }
}
