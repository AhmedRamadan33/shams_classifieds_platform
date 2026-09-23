<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Listing;
use App\Models\Report;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ModerationOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $pending = Listing::query()->where('status', ListingStatus::Pending->value)->count();
        $openReports = Report::query()->where('status', ReportStatus::Open->value)->count();

        return [
            Stat::make(__('app.admin.pending_listings'), $pending)
                ->description(__('app.admin.pending_listings_hint'))
                ->color($pending > 0 ? 'warning' : 'success')
                ->url(ListingResource::getUrl('index')),
            Stat::make(__('app.admin.open_reports'), $openReports)
                ->description(__('app.admin.open_reports_hint'))
                ->color($openReports > 0 ? 'danger' : 'success')
                ->url(ReportResource::getUrl('index')),
        ];
    }
}
