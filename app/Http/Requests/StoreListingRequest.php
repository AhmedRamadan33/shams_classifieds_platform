<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListing;
use App\Models\Listing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreListingRequest extends FormRequest
{
    use ValidatesListing;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Listing::class) ?? false;
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
        return $this->listingRules();
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
        $this->validateListingExtras($validator, creating: true);
    }
}
