<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Listing;
use App\Services\ArabicText;
use App\Services\ListingImages;
use App\Services\ListingSearchText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateListing
{
    public function __construct(
        private readonly SyncListingFieldValues $syncFieldValues,
        private readonly ListingImages $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated listing data
     * @param  array<int, UploadedFile>  $uploads  new images
     * @param  array<int, int|string>  $removeImageIds  ids of existing images to delete
     */
    public function __invoke(Listing $listing, array $data, array $uploads = [], array $removeImageIds = [], ?string $cover = null): Listing
    {
        return DB::transaction(function () use ($listing, $data, $uploads, $removeImageIds, $cover): Listing {
            $category = Category::query()->findOrFail($data['category_id']);
            $priceType = PriceType::from($data['price_type']);

            $listing->fill(Arr::only($data, ['category_id', 'governorate_id', 'city_id', 'title', 'description', 'phone']));
            $listing->price_type = $priceType;
            $listing->price = $priceType->needsPrice() ? ($data['price'] ?? null) : null;
            $listing->slug = ArabicText::slug($data['title']) ?: 'listing';

            $values = ($this->syncFieldValues)($listing, $category->effectiveFields(), $data['fields'] ?? []);

            $this->images->remove($listing, $removeImageIds);
            $created = $this->images->attach($listing, $uploads);
            $this->images->applyCover($listing, $cover, $created);

            $listing->search_text = ListingSearchText::build($listing->title, $listing->description, $values);

            $this->applyStatusAfterEdit($listing);

            $listing->save();

            return $listing;
        });
    }

    /**
     * With moderation on, any edit sends the listing back to the review queue. With moderation
     * off, an edit of a rejected listing publishes it right away.
     */
    private function applyStatusAfterEdit(Listing $listing): void
    {
        if (config('classifieds.require_review')) {
            $listing->status = ListingStatus::Pending;
            $listing->rejection_reason = null;

            return;
        }

        if ($listing->status === ListingStatus::Rejected) {
            $listing->status = ListingStatus::Active;
            $listing->rejection_reason = null;
            $listing->published_at = now();
            $listing->expires_at = now()->addDays((int) config('classifieds.listing_duration_days'));
        }
    }
}
