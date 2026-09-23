<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AdPlacement;
use App\Enums\ListingStatus;
use App\Models\AdBanner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AdBanner::class) ?? false;
    }

    public function rules(): array
    {
        $targetsListing = $this->input('target_type') === 'listing';

        return [
            'placement' => ['required', Rule::in(array_map(fn (AdPlacement $p) => $p->value, AdPlacement::cases()))],
            'title' => ['nullable', 'string', 'max:150'],
            'target_type' => ['required', Rule::in(['url', 'listing'])],
            'target_url' => $targetsListing ? ['nullable'] : ['required', 'url:http,https', 'max:500'],
            'listing_id' => $targetsListing ? ['required', 'integer', $this->ownLiveListing()] : ['nullable'],
            'image' => [
                'required', 'image', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.config('classifieds.max_image_kb'),
                'dimensions:min_width='.config('classifieds.image_min_dimension').',min_height='.config('classifieds.image_min_dimension'),
            ],
        ];
    }

    private function ownLiveListing(): mixed
    {
        return Rule::exists('listings', 'id')->where(fn ($query) => $query
            ->where('user_id', $this->user()->id)
            ->where('status', ListingStatus::Active->value)
            ->whereNull('deleted_at')
            ->where(fn ($inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now())));
    }
}
