<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Governorate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class Geography
{
    public const CACHE_KEY = 'geography.all';

    public static function all(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Governorate::query()->ordered()->with('cities')->get());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
