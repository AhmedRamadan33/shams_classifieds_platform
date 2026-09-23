<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlides;

use App\Filament\Concerns\AdminOnlyResource;
use App\Filament\Resources\HeroSlides\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlides\Pages\EditHeroSlide;
use App\Filament\Resources\HeroSlides\Pages\ListHeroSlides;
use App\Filament\Resources\HeroSlides\Schemas\HeroSlideForm;
use App\Filament\Resources\HeroSlides\Tables\HeroSlidesTable;
use App\Models\HeroSlide;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HeroSlideResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = HeroSlide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('app.admin.hero_slide');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.hero_slides');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.content');
    }

    public static function form(Schema $schema): Schema
    {
        return HeroSlideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HeroSlidesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHeroSlides::route('/'),
            'create' => CreateHeroSlide::route('/create'),
            'edit' => EditHeroSlide::route('/{record}/edit'),
        ];
    }
}
