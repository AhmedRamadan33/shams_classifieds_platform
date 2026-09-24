<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Listing;
use Filament\Widgets\ChartWidget;

class TopCategoriesChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('app.admin.charts.top_categories');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = Listing::query()
            ->toBase()
            ->join('categories', 'categories.id', '=', 'listings.category_id')
            ->selectRaw('categories.name as name, count(*) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'datasets' => [[
                'label' => __('app.admin.charts.listings_label'),
                'data' => $rows->pluck('total')->map(fn ($total): int => (int) $total)->all(),
                'borderRadius' => 6,
            ]],
            'labels' => $rows->pluck('name')->all(),
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
