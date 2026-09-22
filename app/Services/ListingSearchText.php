<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FieldType;
use App\Models\CategoryField;
use Illuminate\Support\Collection;

/**
 * Builds the normalized text the FULLTEXT index searches: title + description + field values,
 * followed by the article-less variant of every word (see ArabicText::withSearchVariants).
 * After changing this, run `php artisan listings:reindex` to refresh the existing listings.
 */
final class ListingSearchText
{
    /**
     * @param  Collection<int, array{field: CategoryField, value: string}>  $fieldValues
     */
    public static function build(string $title, string $description, Collection $fieldValues): string
    {
        $parts = [$title, $description];

        foreach ($fieldValues as $entry) {
            /** @var CategoryField $field */
            $field = $entry['field'];
            $value = $entry['value'];

            if ($field->type === FieldType::Boolean) {
                // "الضمان: نعم" is searchable as "الضمان"; "لا" adds nothing.
                if ($value === '1') {
                    $parts[] = $field->name;
                }

                continue;
            }

            $parts[] = $value;
        }

        return ArabicText::withSearchVariants(ArabicText::normalize(implode(' ', $parts)));
    }
}
