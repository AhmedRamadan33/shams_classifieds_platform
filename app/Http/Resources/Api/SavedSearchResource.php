<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_slug' => $this->category_slug,
            'governorate_slug' => $this->governorate_slug,
            'filters' => $this->filters,
            'notify' => $this->notify,
            'url' => $this->url(),
            'current_count' => $this->when(isset($this->current_count), fn () => (int) $this->current_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
