<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Models\Listing;
use Illuminate\Support\Str;

final class StructuredData
{
    public static function product(Listing $listing, array $images): array
    {
        $offer = [
            '@type' => 'Offer',
            'url' => $listing->url(),
            'priceCurrency' => config('classifieds.currency_code'),
            'availability' => $listing->status === ListingStatus::Active && ! $listing->isExpired()
                ? 'https://schema.org/InStock'
                : 'https://schema.org/SoldOut',
        ];

        if ($listing->price_type === PriceType::Free) {
            $offer['price'] = '0';
        } elseif ($listing->price !== null) {
            $offer['price'] = number_format((float) $listing->price, 2, '.', '');
        }

        if ($listing->expires_at !== null) {
            $offer['priceValidUntil'] = $listing->expires_at->toDateString();
        }

        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $listing->title,
            'description' => Str::limit((string) preg_replace('/\s+/u', ' ', $listing->description), 300),
            'sku' => (string) $listing->id,
            'url' => $listing->url(),
            'category' => $listing->category->name,
            'offers' => $offer,
        ];

        if ($images !== []) {
            $product['image'] = array_map(fn (array $image) => url($image['large']), $images);
        }

        return $product;
    }

    public static function breadcrumbs(array $items): array
    {
        $list = [];

        foreach (array_values($items) as $index => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['label'],
            ];

            if (! empty($item['url'])) {
                $entry['item'] = $item['url'];
            }

            $list[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }
}
