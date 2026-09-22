<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attaches, removes and orders a listing's images.
 */
final class ListingImages
{
    public function __construct(private readonly ImageSanitizer $sanitizer) {}

    /**
     * @param  array<int, UploadedFile>  $uploads
     * @return array<int, Media> the created media, in upload order
     */
    public function attach(Listing $listing, array $uploads): array
    {
        $created = [];

        foreach ($uploads as $upload) {
            $clean = $this->sanitizer->sanitize($upload);

            // The original file name is never used: it is user controlled.
            $created[] = $listing->addMedia($clean['path'])
                ->usingName($listing->title)
                ->usingFileName(Str::random(24).'.'.$clean['extension'])
                ->toMediaCollection(Listing::IMAGES);
        }

        return $created;
    }

    /**
     * Delete some of the listing's own images (ids of other listings' media are ignored).
     *
     * @param  array<int, int|string>  $ids
     */
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

    /**
     * Put the chosen cover first. $cover is "existing:{mediaId}" or "new:{index}" where the index
     * is the position of the image among $newMedia; anything else keeps the current order.
     *
     * @param  array<int, Media>  $newMedia
     */
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
