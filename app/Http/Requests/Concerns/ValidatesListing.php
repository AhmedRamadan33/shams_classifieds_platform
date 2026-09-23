<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\FieldType;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Listing;
use App\Rules\PhoneNumber;
use App\Services\ArabicText;
use App\Services\ListingLimits;
use App\Services\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

trait ValidatesListing
{
    private ?Category $listingCategory = null;

    protected function prepareListingInput(): void
    {
        $merge = [];

        foreach (['title', 'description'] as $key) {
            if (is_string($this->input($key))) {
                $merge[$key] = trim($this->input($key));
            }
        }

        if (is_string($this->input('phone'))) {
            $merge['phone'] = app(PhoneNormalizer::class)->tryNormalize($this->input('phone')) ?? $this->input('phone');
        }

        if (is_string($this->input('price'))) {
            $merge['price'] = $this->cleanNumber($this->input('price'));
        }

        $categoryId = $this->input('category_id');
        $this->listingCategory = is_numeric($categoryId) ? Category::query()->find((int) $categoryId) : null;

        $fields = $this->input('fields');

        if (is_array($fields) && $this->listingCategory !== null) {
            foreach ($this->listingCategory->effectiveFields() as $field) {
                if ($field->type === FieldType::Number && isset($fields[$field->key]) && is_string($fields[$field->key])) {
                    $fields[$field->key] = $this->cleanNumber($fields[$field->key]);
                }
            }

            $merge['fields'] = $fields;
        }

        $this->merge($merge);
    }

    private function cleanNumber(string $value): string
    {
        $value = ArabicText::toLatinDigits(trim($value));

        return str_replace(['٫', '٬', ',', ' '], ['.', '', '', ''], $value);
    }

    protected function listingRules(): array
    {
        $maxImages = (int) config('classifieds.max_images');
        $minDimension = (int) config('classifieds.image_min_dimension');
        $maxDimension = (int) config('classifieds.image_max_dimension');
        $priceType = PriceType::tryFrom((string) $this->input('price_type'));

        $rules = [
            'category_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->listingCategory === null || ! $this->listingCategory->isPostable()) {
                    $fail(__('app.listing.category_invalid'));
                }
            }],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => [
                'nullable',
                'integer',
                Rule::exists('cities', 'id')->where('governorate_id', $this->input('governorate_id')),
            ],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'price_type' => ['required', Rule::enum(PriceType::class)],
            'price' => [
                Rule::requiredIf($priceType?->needsPrice() ?? false),
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'phone' => ['required', 'string', new PhoneNumber],
            'images' => ['nullable', 'array', 'max:'.$maxImages],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.config('classifieds.max_image_kb'),
                "dimensions:min_width={$minDimension},min_height={$minDimension},max_width={$maxDimension},max_height={$maxDimension}",
            ],
            'cover' => ['nullable', 'string', 'regex:/^(existing|new):\d+$/'],
            'fields' => ['nullable', 'array'],
        ];

        if ($this->listingCategory?->isPostable()) {
            foreach ($this->listingCategory->effectiveFields() as $field) {
                $rules['fields.'.$field->key] = [$field->is_required ? 'required' : 'nullable', ...match ($field->type) {
                    FieldType::Number => ['numeric'],
                    FieldType::Select => [Rule::in($field->optionValues())],
                    FieldType::Boolean => ['boolean'],
                    FieldType::Text => ['string', 'max:255'],
                }];
            }
        }

        return $rules;
    }

    protected function listingAttributes(): array
    {
        $attributes = [];

        if ($this->listingCategory !== null) {
            foreach ($this->listingCategory->effectiveFields() as $field) {
                $attributes['fields.'.$field->key] = $field->name;
            }
        }

        return $attributes;
    }

    protected function validateListingExtras(Validator $validator, bool $creating): void
    {
        $validator->after(function (Validator $validator) use ($creating): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $text = ArabicText::normalize($this->input('title').' '.$this->input('description'));

            foreach ((array) config('classifieds.blocked_words') as $word) {
                $word = ArabicText::normalize((string) $word);

                if ($word !== '' && str_contains($text, $word)) {
                    $validator->errors()->add('description', __('app.listing.blocked_word'));

                    return;
                }
            }

            if (! $creating) {
                return;
            }

            $user = $this->user();

            $duplicate = Listing::withTrashed()
                ->where('user_id', $user->id)
                ->where('category_id', $this->input('category_id'))
                ->where('title', $this->input('title'))
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('title', __('app.listing.duplicate'));

                return;
            }

            $limit = ListingLimits::dailyLimitFor($user);

            $today = Listing::withTrashed()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();

            if ($today >= $limit) {
                $validator->errors()->add('limit', __('app.listing.daily_limit', ['limit' => $limit]));
            }
        });
    }

    public function listingData(): array
    {
        $validated = $this->validated();

        return [
            ...collect($validated)->only([
                'category_id', 'governorate_id', 'city_id', 'title', 'description', 'price_type', 'price', 'phone',
            ])->all(),
            'fields' => $validated['fields'] ?? [],
        ];
    }
}
