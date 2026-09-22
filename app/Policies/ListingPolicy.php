<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * Banned users can do nothing; admins and moderators can do everything else.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->isStaff() ? true : null;
    }

    /**
     * Only staff (through before()) can browse every listing, e.g. in the admin panel.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedPhone();
    }

    public function update(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    public function renew(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    public function markSold(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    /**
     * Buying a featured package only makes sense for a listing that is currently live.
     */
    public function feature(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $listing->isPubliclyListed();
    }

    public function restore(User $user, Listing $listing): bool
    {
        return false;
    }

    public function forceDelete(User $user, Listing $listing): bool
    {
        return false;
    }

    private function owns(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id;
    }
}
