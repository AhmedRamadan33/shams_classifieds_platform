<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The active category tree, cached forever and flushed by CategoryObserver on any change.
 * Each returned category has its active `children` relation loaded (recursively).
 */
final class CategoryTree
{
    public const CACHE_KEY = 'categories.tree';

    /**
     * @return Collection<int, Category> root categories
     */
    public static function get(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::build());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Find an active category anywhere in the tree by id.
     */
    public static function find(int $id): ?Category
    {
        return self::flatten(self::get())->firstWhere('id', $id);
    }

    /**
     * Every category of the tree, roots first, depth-first.
     *
     * @param  Collection<int, Category>|null  $nodes
     * @return Collection<int, Category>
     */
    public static function flatten(?Collection $nodes = null): Collection
    {
        $flat = collect();

        foreach ($nodes ?? self::get() as $node) {
            $flat->push($node);
            $flat = $flat->merge(self::flatten($node->children));
        }

        return $flat;
    }

    /**
     * @return Collection<int, Category>
     */
    private static function build(): Collection
    {
        $all = Category::query()->active()->orderBy('sort_order')->orderBy('id')->get();
        $byParent = $all->groupBy('parent_id');

        foreach ($all as $category) {
            $category->setRelation('children', $byParent->get($category->id, collect())->values());
        }

        // Children of an inactive parent are not reachable from the roots, so they are left out.
        return $byParent->get('', collect())->values();
    }
}
