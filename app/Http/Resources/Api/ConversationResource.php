<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $other = $me !== null ? $this->otherParticipant($me) : null;

        return [
            'id' => $this->id,
            'listing' => $this->whenLoaded('listing', fn () => $this->listing ? [
                'id' => $this->listing->id, 'title' => $this->listing->title,
            ] : null),
            'other_participant' => $other !== null ? ['id' => $other->id, 'name' => $other->name] : null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
