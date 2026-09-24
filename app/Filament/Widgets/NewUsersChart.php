<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CountsPerDay;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class NewUsersChart extends ChartWidget
{
    use CountsPerDay;

    protected static ?int $sort = 10;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return __('app.admin.charts.users_per_day');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [[
                'label' => __('app.admin.charts.users_label'),
                'data' => $this->perDay(User::query(), 30),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $this->dayLabels(30),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
