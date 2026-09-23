<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernorateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'cities' => $this->whenLoaded('cities', fn () => $this->cities->map(fn ($city) => [
                'id' => $city->id,
                'name' => $city->name,
            ])->values()),
        ];
    }
}
