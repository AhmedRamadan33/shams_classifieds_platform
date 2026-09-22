<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Pages;

use App\Filament\Resources\StaticPages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
