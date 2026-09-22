<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Reports are only for the moderation team: admins and moderators can do everything, nobody else anything.
 */
class ReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_banned ? false : ($user->isStaff() ? true : false);
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Report $report): bool
    {
        return false;
    }
}
