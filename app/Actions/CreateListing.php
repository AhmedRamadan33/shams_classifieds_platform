<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\ArabicText;
use App\Services\ListingImages;
use App\Services\ListingSearchText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateListing
{
    public function __construct(
        private readonly SyncListingFieldValues $syncFieldValues,
        private readonly ListingImages $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated listing data (see StoreListingRequest::listingData())
     * @param  array<int, UploadedFile>  $uploads
     */
    public function __invoke(User $user, array $data, array $uploads = [], ?string $cover = null): Listing
    {
        return DB::transaction(function () use ($user, $data, $uploads, $cover): Listing {
            $category = Category::query()->findOrFail($data['category_id']);
            $priceType = PriceType::from($data['price_type']);

            $listing = new Listing(Arr::only($data, ['category_id', 'governorate_id', 'city_id', 'title', 'description', 'phone']));
            $listing->user_id = $user->id;
            $listing->price_type = $priceType;
            $listing->price = $priceType->needsPrice() ? ($data['price'] ?? null) : null;
            $listing->status = ListingStatus::Pending;
            $listing->slug = ArabicText::slug($data['title']) ?: 'listing';
            $listing->save();

            $values = ($this->syncFieldValues)($listing, $category->effectiveFields(), $data['fields'] ?? []);

            $created = $this->images->attach($listing, $uploads);
            $this->images->applyCover($listing, $cover, $created);

            $listing->search_text = ListingSearchText::build($listing->title, $listing->description, $values);

            if (! config('classifieds.require_review')) {
                $listing->status = ListingStatus::Active;
                $listing->published_at = now();
                $listing->expires_at = now()->addDays((int) config('classifieds.listing_duration_days'));
            }

            $listing->save();

            return $listing;
        });
    }
}
