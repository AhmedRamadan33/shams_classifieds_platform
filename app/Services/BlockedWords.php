<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The moderation check applied to listing text (ValidatesListing) and to chat messages
 * (SendMessageRequest): does the (Arabic-normalized) text contain one of config('classifieds.blocked_words')?
 */
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
