<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AdBannerStatus;
use App\Models\AdBanner;

final class ExpireAdBanners
{
    public function __invoke(): int
    {
        return AdBanner::query()
            ->where('status', AdBannerStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => AdBannerStatus::Expired->value]);
    }
}
