<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdPackages\Pages;

use App\Filament\Resources\AdPackages\AdPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdPackage extends EditRecord
{
    protected static string $resource = AdPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
