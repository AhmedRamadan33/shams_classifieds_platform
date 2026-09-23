<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\FieldType;
use App\Models\CategoryField;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use Illuminate\Support\Collection;

final class SyncListingFieldValues
{
    public function __invoke(Listing $listing, Collection $fields, array $input): Collection
    {
        $stored = collect();

        foreach ($fields as $field) {
            $value = $this->normalize($field, $input[$field->key] ?? null);

            if ($value === null) {
                ListingFieldValue::query()
                    ->where('listing_id', $listing->id)
                    ->where('category_field_id', $field->id)
                    ->delete();

                continue;
            }

            ListingFieldValue::query()->updateOrCreate(
                ['listing_id' => $listing->id, 'category_field_id' => $field->id],
                ['value' => $value],
            );

            $stored->put($field->key, ['field' => $field, 'value' => $value]);
        }

        ListingFieldValue::query()
            ->where('listing_id', $listing->id)
            ->whereNotIn('category_field_id', $fields->pluck('id')->all())
            ->delete();

        return $stored;
    }

    private function normalize(CategoryField $field, mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;

        return match ($field->type) {
            FieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            FieldType::Number => $this->normalizeNumber((string) $value),
            default => (string) $value,
        };
    }

    private function normalizeNumber(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
