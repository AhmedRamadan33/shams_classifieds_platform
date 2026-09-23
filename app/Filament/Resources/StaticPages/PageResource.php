<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages;

use App\Filament\Concerns\AdminOnlyResource;
use App\Filament\Resources\StaticPages\Pages\CreatePage;
use App\Filament\Resources\StaticPages\Pages\EditPage;
use App\Filament\Resources\StaticPages\Pages\ListPages;
use App\Filament\Resources\StaticPages\Schemas\PageForm;
use App\Filament\Resources\StaticPages\Tables\PagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pages';

    public static function getModelLabel(): string
    {
        return __('app.admin.page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.pages');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.content');
    }

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
