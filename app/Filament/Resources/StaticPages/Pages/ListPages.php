<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Pages;

use App\Filament\Resources\StaticPages\PageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
