<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AdBannerStatus;
use App\Models\AdBanner;
use App\Models\User;

class AdBannerPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->isStaff() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AdBanner $adBanner): bool
    {
        return $this->owns($user, $adBanner);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedPhone();
    }

    public function purchase(User $user, AdBanner $adBanner): bool
    {
        return $this->owns($user, $adBanner)
            && in_array($adBanner->status, [AdBannerStatus::Approved, AdBannerStatus::Active, AdBannerStatus::Expired], true)
            && $adBanner->hasLiveTarget();
    }

    private function owns(User $user, AdBanner $adBanner): bool
    {
        return $adBanner->user_id === $user->id;
    }
}
