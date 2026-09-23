<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdBanners\Pages;

use App\Filament\Resources\AdBanners\AdBannerResource;
use Filament\Resources\Pages\ListRecords;

class ListAdBanners extends ListRecords
{
    protected static string $resource = AdBannerResource::class;
}
