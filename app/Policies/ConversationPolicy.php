<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }
}
