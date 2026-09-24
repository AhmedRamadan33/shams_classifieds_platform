<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ReportStatus;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\Report;
use App\Models\Review;
use App\Models\SavedSearch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EngagementOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected static bool $isLazy = false;

    protected int|array|null $columns = ['@xl' => 3, '!@lg' => 2];

    protected function getHeading(): ?string
    {
        return __('app.admin.stats.engagement');
    }

    protected function getStats(): array
    {
        $reviews = Review::query()->visible();
        $averageRating = (float) (clone $reviews)->avg('rating');

        return [
            Stat::make(__('app.admin.stats.favorites'), number_format(Favorite::query()->count()))
                ->description(__('app.admin.stats.favorites_hint'))
                ->color('danger'),
            Stat::make(__('app.admin.stats.conversations'), number_format(Conversation::query()->count()))
                ->description(__('app.admin.stats.conversations_hint'))
                ->color('info'),
            Stat::make(__('app.admin.stats.messages'), number_format(Message::query()->count()))
                ->description(__('app.admin.stats.messages_hint', ['unread' => number_format(Message::query()->unread()->count())]))
                ->color('primary'),
            Stat::make(__('app.admin.stats.reviews'), number_format((clone $reviews)->count()))
                ->description(__('app.admin.stats.reviews_hint', ['average' => number_format($averageRating, 1)]))
                ->color('warning'),
            Stat::make(__('app.admin.stats.saved_searches'), number_format(SavedSearch::query()->count()))
                ->description(__('app.admin.stats.saved_searches_hint', ['notify' => number_format(SavedSearch::query()->where('notify', true)->count())]))
                ->color('success'),
            Stat::make(__('app.admin.stats.total_reports'), number_format(Report::query()->count()))
                ->description(__('app.admin.stats.total_reports_hint', ['resolved' => number_format(Report::query()->where('status', ReportStatus::Resolved->value)->count())]))
                ->color('gray'),
        ];
    }
}
