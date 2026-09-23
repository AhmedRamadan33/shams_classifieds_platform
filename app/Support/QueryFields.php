<?php

declare(strict_types=1);

namespace App\Support;

final class QueryFields
{
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
