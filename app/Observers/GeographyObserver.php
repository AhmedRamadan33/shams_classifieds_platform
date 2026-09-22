<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Geography;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered on Governorate and City: any change flushes the cached geography.
 */
class GeographyObserver
{
    public function saved(Model $model): void
    {
        Geography::flush();
    }

    public function deleted(Model $model): void
    {
        Geography::flush();
    }
}
