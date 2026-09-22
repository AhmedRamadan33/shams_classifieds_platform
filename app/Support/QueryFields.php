<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flattens a (possibly nested) query array into HTML form field names, so a small GET form can
 * carry the current filters along: ['f' => ['year' => ['min' => 2015]]] => [['f[year][min]', '2015']].
 */
final class QueryFields
{
    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $except  top-level keys to leave out
     * @return list<array{0: string, 1: string}>
     */
    public static function flatten(array $query, array $except = []): array
    {
        $fields = [];

        foreach ($query as $key => $value) {
            if (in_array($key, $except, true)) {
                continue;
            }

            self::walk((string) $key, $value, $fields);
        }

        return $fields;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $fields
     */
    private static function walk(string $name, mixed $value, array &$fields): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                self::walk($name.'['.$key.']', $child, $fields);
            }

            return;
        }

        if (is_scalar($value) && (string) $value !== '') {
            $fields[] = [$name, (string) $value];
        }
    }
}
