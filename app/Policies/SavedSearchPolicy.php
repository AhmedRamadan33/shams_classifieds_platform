<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;

class SavedSearchPolicy
{
    public function update(User $user, SavedSearch $savedSearch): bool
    {
        return $savedSearch->user_id === $user->id;
    }

    public function delete(User $user, SavedSearch $savedSearch): bool
    {
        return $savedSearch->user_id === $user->id;
    }
}
