<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ListingStatus;
use App\Models\Listing;
use Filament\Widgets\ChartWidget;

class ListingsByStatusChart extends ChartWidget
{
    private const COLORS = [
        'pending' => '#f59e0b',
        'active' => '#10b981',
        'rejected' => '#ef4444',
        'expired' => '#94a3b8',
        'sold' => '#3b82f6',
    ];

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('app.admin.charts.listings_by_status');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Listing::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $statuses = ListingStatus::cases();

        return [
            'datasets' => [[
                'data' => array_map(fn (ListingStatus $status): int => (int) ($counts[$status->value] ?? 0), $statuses),
                'backgroundColor' => array_map(fn (ListingStatus $status): string => self::COLORS[$status->value], $statuses),
            ]],
            'labels' => array_map(fn (ListingStatus $status): string => $status->label(), $statuses),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
