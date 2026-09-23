<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AdBannerStatus;
use App\Models\AdBanner;
use App\Notifications\AdBannerRejected;

final class RejectAdBanner
{
    public function __invoke(AdBanner $adBanner, string $reason): AdBanner
    {
        $adBanner->forceFill([
            'status' => AdBannerStatus::Rejected,
            'rejection_reason' => trim($reason),
        ])->save();

        $adBanner->user->notify(new AdBannerRejected($adBanner, trim($reason)));

        return $adBanner;
    }
}
