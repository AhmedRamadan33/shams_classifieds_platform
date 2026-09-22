<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'notify' => ['nullable', 'boolean'],
            'category_slug' => ['nullable', 'string', 'max:100'],
            'governorate_slug' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $limit = (int) config('classifieds.saved_search_limit');

            if (SavedSearch::query()->where('user_id', $this->user()->id)->count() >= $limit) {
                $validator->errors()->add('name', __('app.saved_searches.limit_reached', ['limit' => $limit]));
            }
        });
    }

    /**
     * Everything the results page's filters sent along, minus the fields this form itself defines.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->except(['name', 'notify', 'category_slug', 'governorate_slug', '_token']);
    }
}
