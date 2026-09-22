<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'icon' => $this->icon,
            'is_postable' => $this->isPostable(),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
