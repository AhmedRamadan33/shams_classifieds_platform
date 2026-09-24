<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

trait CountsPerDay
{
    protected function perDay(EloquentBuilder|Builder $query, int $days, string $column = 'created_at', string $aggregate = 'count(*)'): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = $query
            ->where($column, '>=', $start)
            ->selectRaw("date({$column}) as day, {$aggregate} as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(fn (int $offset): float => (float) ($rows[$start->copy()->addDays($offset)->toDateString()] ?? 0))
            ->all();
    }

    protected function dayLabels(int $days): array
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $ago): string => now()->subDays($ago)->format('d/m'))
            ->all();
    }
}
