<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\FieldType;
use App\Models\CategoryField;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use Illuminate\Support\Collection;

/**
 * Stores a listing's dynamic field values: one row per filled field, and no rows for fields that
 * are empty or no longer apply (for example after the category changed).
 */
final class SyncListingFieldValues
{
    /**
     * @param  Collection<int, CategoryField>  $fields  effective fields of the listing's category
     * @param  array<string, mixed>  $input  submitted values keyed by field key
     * @return Collection<string, array{field: CategoryField, value: string}> stored values keyed by field key
     */
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

    /**
     * "2020.50" -> "2020.5", "2020.00" -> "2020" so equal numbers are stored identically.
     */
    private function normalizeNumber(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
