<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class CategoryTree
{
    public const CACHE_KEY = 'categories.tree';

    public static function get(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::build());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function find(int $id): ?Category
    {
        return self::flatten(self::get())->firstWhere('id', $id);
    }

    public static function flatten(?Collection $nodes = null): Collection
    {
        $flat = collect();

        foreach ($nodes ?? self::get() as $node) {
            $flat->push($node);
            $flat = $flat->merge(self::flatten($node->children));
        }

        return $flat;
    }

    private static function build(): Collection
    {
        $all = Category::query()->active()->orderBy('sort_order')->orderBy('id')->get();
        $byParent = $all->groupBy('parent_id');

        foreach ($all as $category) {
            $category->setRelation('children', $byParent->get($category->id, collect())->values());
        }

        return $byParent->get('', collect())->values();
    }
}
