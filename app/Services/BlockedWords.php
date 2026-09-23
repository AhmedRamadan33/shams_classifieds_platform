<?php

declare(strict_types=1);

namespace App\Services;

final class BlockedWords
{
    public static function containsAny(string $text): bool
    {
        $normalized = ArabicText::normalize($text);

        foreach ((array) config('classifieds.blocked_words') as $word) {
            $word = ArabicText::normalize((string) $word);

            if ($word !== '' && str_contains($normalized, $word)) {
                return true;
            }
        }

        return false;
    }
}
