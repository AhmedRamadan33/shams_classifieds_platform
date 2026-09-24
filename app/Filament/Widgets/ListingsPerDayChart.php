<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CountsPerDay;
use App\Models\Listing;
use Filament\Widgets\ChartWidget;

class ListingsPerDayChart extends ChartWidget
{
    use CountsPerDay;

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('app.admin.charts.listings_per_day');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [[
                'label' => __('app.admin.charts.listings_label'),
                'data' => $this->perDay(Listing::query(), 30),
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
