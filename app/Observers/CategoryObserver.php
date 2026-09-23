<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\CategoryTree;
use Illuminate\Database\Eloquent\Model;

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
