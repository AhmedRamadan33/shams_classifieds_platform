<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Listing;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class UsersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    protected static bool $isLazy = false;

    protected int|array|null $columns = ['@xl' => 4, '!@lg' => 2];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('app.admin.stats.users');
    }

    protected function getStats(): array
    {
        $total = User::query()->count();
        $verified = User::query()->whereNotNull('phone_verified_at')->count();

        return [
            Stat::make(__('app.admin.stats.total_users'), number_format($total))
                ->description(__('app.admin.stats.total_users_hint'))
                ->color('primary'),
            Stat::make(__('app.admin.stats.new_users_week'), number_format(User::query()->where('created_at', '>=', now()->subDays(7)->startOfDay())->count()))
                ->description(__('app.admin.stats.new_users_week_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.verified_users'), number_format($verified))
                ->description(__('app.admin.stats.verified_users_hint', ['percent' => $total > 0 ? (int) round($verified / $total * 100) : 0]))
                ->color('success'),
            Stat::make(__('app.admin.stats.banned_users'), number_format(User::query()->where('is_banned', true)->count()))
                ->description(__('app.admin.stats.banned_users_hint'))
                ->color('danger'),
            Stat::make(__('app.admin.stats.sellers'), number_format(Listing::query()->distinct()->count('user_id')))
                ->description(__('app.admin.stats.sellers_hint'))
                ->color('success'),
            Stat::make(__('app.admin.stats.stores'), number_format(Store::query()->count()))
                ->description(__('app.admin.stats.stores_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.active_subscriptions'), number_format(Subscription::query()->active()->count()))
                ->description(__('app.admin.stats.active_subscriptions_hint'))
                ->color('warning'),
            Stat::make(__('app.admin.stats.staff'), number_format(User::query()->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [User::ROLE_ADMIN, User::ROLE_MODERATOR]))->count()))
                ->description(__('app.admin.stats.staff_hint'))
                ->color('gray'),
        ];
    }
}
