<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\MessagingException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use App\Services\BlockedWords;

final class SendMessage
{
    public function __invoke(User $sender, Conversation $conversation, string $body): Message
    {
        if (! $conversation->isParticipant($sender)) {
            throw MessagingException::listingUnavailable();
        }

        if (BlockedWords::containsAny($body)) {
            throw MessagingException::blockedContent();
        }

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        $conversation->otherParticipant($sender)->notify(new NewMessageReceived($message));

        return $message;
    }
}
