<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListing;
use App\Services\ListingImages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateListingRequest extends FormRequest
{
    use ValidatesListing;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('listing')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareListingInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->listingRules(),
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->listingAttributes();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateListingExtras($validator, creating: false);

        // Existing images that stay + newly uploaded ones must fit the limit.
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $listing = $this->route('listing');
            $remaining = app(ListingImages::class)->count($listing)
                - $listing->media()->whereIn('id', array_map('intval', (array) $this->input('remove_images', [])))->count();
            $total = $remaining + count($this->file('images', []));
            $max = (int) config('classifieds.max_images');

            if ($total > $max) {
                $validator->errors()->add('images', __('app.listing.images_max', ['max' => $max]));
            }
        });
    }
}
