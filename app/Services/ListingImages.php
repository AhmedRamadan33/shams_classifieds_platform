<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ListingImages
{
    public function __construct(private readonly ImageSanitizer $sanitizer) {}

    public function attach(Listing $listing, array $uploads): array
    {
        $created = [];

        foreach ($uploads as $upload) {
            $clean = $this->sanitizer->sanitize($upload);

            $created[] = $listing->addMedia($clean['path'])
                ->usingName($listing->title)
                ->usingFileName(Str::random(24).'.'.$clean['extension'])
                ->toMediaCollection(Listing::IMAGES);
        }

        return $created;
    }

    public function remove(Listing $listing, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $ids = array_map('intval', $ids);

        $listing->media()
            ->where('collection_name', Listing::IMAGES)
            ->whereIn('id', $ids)
            ->get()
            ->each(fn (Media $media) => $media->delete());
    }

    public function applyCover(Listing $listing, ?string $cover, array $newMedia): void
    {
        $ids = $listing->media()
            ->where('collection_name', Listing::IMAGES)
            ->orderBy('order_column')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $coverId = null;

        if ($cover !== null && preg_match('/^(existing|new):(\d+)$/', $cover, $match) === 1) {
            $coverId = $match[1] === 'existing'
                ? (int) $match[2]
                : ($newMedia[(int) $match[2]]->id ?? null);
        }

        if ($coverId !== null && in_array($coverId, $ids, true)) {
            $ids = [$coverId, ...array_values(array_diff($ids, [$coverId]))];
        }

        if ($ids !== []) {
            Media::setNewOrder($ids);
        }
    }

    public function count(Listing $listing): int
    {
        return $listing->media()->where('collection_name', Listing::IMAGES)->count();
    }
}
