<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Widgets\Concerns\CountsPerDay;
use App\Models\AdBanner;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class RevenueOverview extends StatsOverviewWidget
{
    use CountsPerDay;

    protected static ?int $sort = 8;

    protected static bool $isLazy = false;

    protected int|array|null $columns = ['@xl' => 5, '!@lg' => 2];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('app.admin.stats.revenue');
    }

    protected function getStats(): array
    {
        $paid = fn (): Builder => Payment::query()->where('status', PaymentStatus::Paid->value);
        $revenue = fn (Builder $query): float => (float) $query->sum('amount');
        $lastTwoWeeks = $this->perDay(Payment::query()->where('status', PaymentStatus::Paid->value), 14, 'paid_at', 'sum(amount)');

        return [
            Stat::make(__('app.admin.stats.revenue_total'), $this->money($revenue($paid())))
                ->description(__('app.admin.stats.revenue_total_hint'))
                ->chart($lastTwoWeeks)
                ->color('success'),
            Stat::make(__('app.admin.stats.revenue_month'), $this->money($revenue($paid()->where('paid_at', '>=', now()->startOfMonth()))))
                ->description(__('app.admin.stats.revenue_month_hint'))
                ->color('success'),
            Stat::make(__('app.admin.stats.revenue_today'), $this->money($revenue($paid()->where('paid_at', '>=', now()->startOfDay()))))
                ->description(__('app.admin.stats.revenue_today_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.paid_payments'), number_format($paid()->count()))
                ->description(__('app.admin.stats.paid_payments_hint', ['total' => number_format(Payment::query()->count())]))
                ->color('primary'),
            Stat::make(__('app.admin.stats.pending_payments'), number_format(Payment::query()->where('status', PaymentStatus::Pending->value)->count()))
                ->description(__('app.admin.stats.pending_payments_hint'))
                ->color('warning'),
            Stat::make(__('app.admin.stats.featured_revenue'), $this->money($revenue($paid()->whereNotNull('listing_id'))))
                ->description(__('app.admin.stats.featured_revenue_hint'))
                ->color('warning'),
            Stat::make(__('app.admin.stats.subscription_revenue'), $this->money($revenue($paid()->whereNotNull('subscription_id'))))
                ->description(__('app.admin.stats.subscription_revenue_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.banner_revenue'), $this->money($revenue($paid()->whereNotNull('ad_banner_id'))))
                ->description(__('app.admin.stats.banner_revenue_hint'))
                ->color('primary'),
            Stat::make(__('app.admin.stats.active_banners'), number_format(AdBanner::query()->currentlyActive()->count()))
                ->description(__('app.admin.stats.active_banners_hint'))
                ->color('success'),
            Stat::make(__('app.admin.stats.banner_clicks'), number_format((int) AdBanner::query()->sum('clicks')))
                ->description(__('app.admin.stats.banner_clicks_hint'))
                ->color('gray'),
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, floor($amount) === $amount ? 0 : 2).' '.config('classifieds.currency_label');
    }
}
