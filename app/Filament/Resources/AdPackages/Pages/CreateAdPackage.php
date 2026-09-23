<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdPackages\Pages;

use App\Filament\Resources\AdPackages\AdPackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdPackage extends CreateRecord
{
    protected static string $resource = AdPackageResource::class;
}
