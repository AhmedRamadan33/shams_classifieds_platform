<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FieldType;
use Illuminate\Support\Collection;

final class ListingSearchText
{
    public static function build(string $title, string $description, Collection $fieldValues): string
    {
        $parts = [$title, $description];

        foreach ($fieldValues as $entry) {
            $field = $entry['field'];
            $value = $entry['value'];

            if ($field->type === FieldType::Boolean) {
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
