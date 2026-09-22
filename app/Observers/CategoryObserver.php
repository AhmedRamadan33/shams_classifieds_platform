<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\CategoryTree;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the cached category tree in sync: any change to a category or one of its fields flushes it.
 * Registered on both Category and CategoryField through #[ObservedBy].
 */
class CategoryObserver
{
    public function saved(Model $model): void
    {
        CategoryTree::flush();
    }

    public function deleted(Model $model): void
    {
        CategoryTree::flush();
    }
}
