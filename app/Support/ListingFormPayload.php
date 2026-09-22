<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use App\Services\CategoryTree;
use App\Services\Geography;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Everything the Alpine listing form needs, as one JSON-serializable array: the category tree,
 * governorates with their cities, limits, translated messages, and the initial values (submitted
 * input after a validation error, the listing being edited, or defaults).
 */
final class ListingFormPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(User $user, ?Listing $listing = null): array
    {
        $editing = $listing !== null;

        return [
            'mode' => $editing ? 'edit' : 'create',
            'tree' => self::tree(CategoryTree::get()),
            'governorates' => self::governorates(),
            'priceTypes' => collect(PriceType::cases())->map(fn (PriceType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'needsPrice' => $type->needsPrice(),
            ])->all(),
            'limits' => [
                'maxImages' => (int) config('classifieds.max_images'),
                'maxImageKb' => (int) config('classifieds.max_image_kb'),
            ],
            'currency' => config('classifieds.currency_label'),
            'urls' => ['fields' => route('categories.fields', ['category' => '__ID__'])],
            'values' => self::values($user, $listing),
            'existingImages' => $editing ? self::existingImages($listing) : [],
            'errors' => collect(session('errors')?->getBag('default')->getMessages() ?? [])
                ->map(fn (array $messages) => $messages[0])
                ->all(),
            'messages' => __('app.listing_form'),
        ];
    }

    /**
     * Recursive category tree: a node without children is a leaf and can receive listings.
     *
     * @param  Collection<int, Category>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function tree(Collection $nodes): array
    {
        return $nodes->map(fn (Category $node) => [
            'id' => $node->id,
            'name' => $node->name,
            'iconPath' => CategoryIcons::path($node->icon),
            'children' => self::tree($node->children),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function governorates(): array
    {
        return Geography::all()->map(fn (Governorate $governorate) => [
            'id' => $governorate->id,
            'name' => $governorate->name,
            'cities' => $governorate->cities->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])->values()->all(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(User $user, ?Listing $listing): array
    {
        $fields = $listing?->fieldValues
            ->mapWithKeys(fn ($value) => [$value->field->key => $value->value])
            ->all() ?? [];

        $categoryId = old('category_id', $listing?->category_id);

        return [
            'categoryId' => $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null,
            'title' => old('title', $listing?->title ?? ''),
            'description' => old('description', $listing?->description ?? ''),
            'priceType' => old('price_type', $listing?->price_type->value ?? PriceType::Fixed->value),
            'price' => old('price', $listing?->price !== null ? rtrim(rtrim((string) $listing->price, '0'), '.') : ''),
            'governorateId' => (string) old('governorate_id', $listing?->governorate_id ?? ''),
            'cityId' => (string) old('city_id', $listing?->city_id ?? ''),
            'phone' => old('phone', $listing?->phone ?? $user->phone),
            'fields' => (object) old('fields', $fields),
            'cover' => old('cover'),
        ];
    }

    /**
     * @return list<array{id: int, url: string}>
     */
    private static function existingImages(Listing $listing): array
    {
        /** @var Collection<int, Media> $media */
        $media = $listing->getMedia(Listing::IMAGES);

        return $media->map(fn (Media $image) => [
            'id' => $image->id,
            'url' => $image->hasGeneratedConversion('thumb') ? $image->getUrl('thumb') : $image->getUrl(),
        ])->values()->all();
    }
}
