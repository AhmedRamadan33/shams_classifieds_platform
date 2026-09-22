<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Governorate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Governorates with their cities, cached forever and flushed by GeographyObserver on any change.
 */
final class Geography
{
    public const CACHE_KEY = 'geography.all';

    /**
     * @return Collection<int, Governorate> ordered governorates, each with its ordered `cities` loaded
     */
    public static function all(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Governorate::query()->ordered()->with('cities')->get());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
