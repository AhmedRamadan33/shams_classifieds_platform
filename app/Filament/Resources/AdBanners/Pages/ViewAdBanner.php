<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdBanners\Pages;

use App\Filament\Resources\AdBanners\Actions\AdBannerActions;
use App\Filament\Resources\AdBanners\AdBannerResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAdBanner extends ViewRecord
{
    protected static string $resource = AdBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdBannerActions::approve()->after(fn () => $this->refreshFormData(['status', 'rejection_reason'])),
            AdBannerActions::reject()->after(fn () => $this->refreshFormData(['status', 'rejection_reason'])),
        ];
    }
}
