<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Enums\FieldType;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_type' => $this->price_type->value,
            'formatted_price' => $this->formattedPrice(withType: true),
            'status' => $this->status->value,
            'is_featured' => $this->isFeatured(),
            'views' => $this->views,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug,
            ]),
            'governorate' => $this->whenLoaded('governorate', fn () => [
                'id' => $this->governorate->id, 'name' => $this->governorate->name, 'slug' => $this->governorate->slug,
            ]),
            'city' => $this->whenLoaded('city', fn () => $this->city ? ['id' => $this->city->id, 'name' => $this->city->name] : null),
            'seller' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'average_rating' => $this->user->averageRating(),
                'reviews_count' => $this->user->reviewsCount(),
            ]),
            'fields' => $this->whenLoaded('fieldValues', fn () => $this->fieldValues
                ->filter(fn ($row) => $row->field !== null)
                ->map(fn ($row) => [
                    'key' => $row->field->key,
                    'name' => $row->field->name,
                    'type' => $row->field->type->value,
                    'value' => $row->field->type === FieldType::Boolean ? $row->value === '1' : $row->value,
                    'unit' => $row->field->unit,
                ])->values()),
            'images' => $this->getMedia(Listing::IMAGES)->map(fn ($media) => [
                'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
                'medium' => $media->hasGeneratedConversion('medium') ? $media->getUrl('medium') : $media->getUrl(),
                'large' => $media->hasGeneratedConversion('large') ? $media->getUrl('large') : $media->getUrl(),
            ])->values(),
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'url' => $this->url(),
        ];
    }
}
