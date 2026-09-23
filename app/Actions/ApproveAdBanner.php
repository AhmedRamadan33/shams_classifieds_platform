<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AdBannerStatus;
use App\Models\AdBanner;
use App\Notifications\AdBannerApproved;

final class ApproveAdBanner
{
    public function __invoke(AdBanner $adBanner): AdBanner
    {
        $adBanner->forceFill([
            'status' => AdBannerStatus::Approved,
            'rejection_reason' => null,
        ])->save();

        $adBanner->user->notify(new AdBannerApproved($adBanner));

        return $adBanner;
    }
}
