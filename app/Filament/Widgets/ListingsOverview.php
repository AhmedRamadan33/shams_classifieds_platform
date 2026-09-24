<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ListingStatus;
use App\Filament\Widgets\Concerns\CountsPerDay;
use App\Models\Listing;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListingsOverview extends StatsOverviewWidget
{
    use CountsPerDay;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|array|null $columns = ['@xl' => 5, '!@lg' => 2];

    protected function getHeading(): ?string
    {
        return __('app.admin.stats.listings');
    }

    protected function getStats(): array
    {
        $counts = Listing::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $count = fn (ListingStatus $status): int => (int) ($counts[$status->value] ?? 0);

        $total = (int) $counts->sum();
        $views = (int) Listing::query()->sum('views');
        $lastWeek = $this->perDay(Listing::query(), 7);
        $today = (int) end($lastWeek);

        return [
            Stat::make(__('app.admin.stats.total_listings'), number_format($total))
                ->description(__('app.admin.stats.total_listings_hint'))
                ->chart($lastWeek)
                ->color('primary'),
            Stat::make(__('app.admin.stats.active_listings'), number_format($count(ListingStatus::Active)))
                ->description(__('app.admin.stats.active_listings_hint'))
                ->color('success'),
            Stat::make(__('app.admin.stats.featured_listings'), number_format(Listing::query()->featured()->count()))
                ->description(__('app.admin.stats.featured_listings_hint'))
                ->color('warning'),
            Stat::make(__('app.admin.stats.listings_today'), number_format($today))
                ->description(__('app.admin.stats.listings_today_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.listings_week'), number_format((int) array_sum($lastWeek)))
                ->description(__('app.admin.stats.listings_week_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.rejected_listings'), number_format($count(ListingStatus::Rejected)))
                ->description(__('app.admin.stats.rejected_listings_hint'))
                ->color('danger'),
            Stat::make(__('app.admin.stats.expired_listings'), number_format($count(ListingStatus::Expired)))
                ->description(__('app.admin.stats.expired_listings_hint'))
                ->color('gray'),
            Stat::make(__('app.admin.stats.sold_listings'), number_format($count(ListingStatus::Sold)))
                ->description(__('app.admin.stats.sold_listings_hint'))
                ->color('gray'),
            Stat::make(__('app.admin.stats.total_views'), number_format($views))
                ->description(__('app.admin.stats.total_views_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.average_views'), number_format($total > 0 ? $views / $total : 0, 1))
                ->description(__('app.admin.stats.average_views_hint'))
                ->color('gray'),
        ];
    }
}
