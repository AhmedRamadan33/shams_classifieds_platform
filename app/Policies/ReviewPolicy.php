<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_banned ? false : ($user->isStaff() ? true : false);
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Review $review): bool
    {
        return false;
    }
}
