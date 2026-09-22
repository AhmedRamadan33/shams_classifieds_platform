<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Governorate
 */
class GovernorateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
