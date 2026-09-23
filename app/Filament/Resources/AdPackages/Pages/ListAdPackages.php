<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdPackages\Pages;

use App\Filament\Resources\AdPackages\AdPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdPackages extends ListRecords
{
    protected static string $resource = AdPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
