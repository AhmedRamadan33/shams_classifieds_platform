<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => $this->avatarUrl(),
            'phone_verified' => $this->hasVerifiedPhone(),
            'notify_email' => (bool) $this->notify_email,
            'notify_whatsapp' => (bool) $this->notify_whatsapp,
            'is_staff' => $this->isStaff(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
