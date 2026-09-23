<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AdPlacement;
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
        return [
            'placement' => ['required', Rule::in(array_map(fn (AdPlacement $p) => $p->value, AdPlacement::cases()))],
            'title' => ['nullable', 'string', 'max:150'],
            'target_url' => ['required', 'url:http,https', 'max:500'],
            'image' => [
                'required', 'image', 'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.config('classifieds.max_image_kb'),
                'dimensions:min_width='.config('classifieds.image_min_dimension').',min_height='.config('classifieds.image_min_dimension'),
            ],
        ];
    }
}
