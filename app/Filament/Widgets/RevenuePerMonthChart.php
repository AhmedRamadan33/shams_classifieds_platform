<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;

class RevenuePerMonthChart extends ChartWidget
{
    protected static ?int $sort = 9;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return __('app.admin.charts.revenue_per_month');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = now()->subMonths(5)->startOfMonth();

        $rows = Payment::query()
            ->toBase()
            ->where('status', PaymentStatus::Paid->value)
            ->where('paid_at', '>=', $start)
            ->selectRaw("date_format(paid_at, '%Y-%m') as month, sum(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $months = collect(range(0, 5))->map(fn (int $offset) => $start->copy()->addMonths($offset));

        return [
            'datasets' => [[
                'label' => __('app.admin.charts.revenue_label'),
                'data' => $months->map(fn ($month): float => (float) ($rows[$month->format('Y-m')] ?? 0))->all(),
                'borderRadius' => 6,
            ]],
            'labels' => $months->map(fn ($month): string => $month->format('m/Y'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
