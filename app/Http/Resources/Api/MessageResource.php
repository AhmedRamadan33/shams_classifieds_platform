<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'mine' => $this->sender_id === $request->user()?->id,
            'sender' => $this->whenLoaded('sender', fn () => ['id' => $this->sender->id, 'name' => $this->sender->name]),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
