<?php

declare(strict_types=1);

namespace App\Services;

final class ArabicText
{
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ی' => 'ي',
        'ة' => 'ه',
        'ؤ' => 'و',
        'ک' => 'ك',
    ];

    public static function toLatinDigits(string $text): string
    {
        return strtr($text, self::DIGITS);
    }

    public static function stripMarks(string $text): string
    {
        return (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text);
    }

    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = self::stripMarks($text);
        $text = strtr($text, self::LETTERS);
        $text = self::toLatinDigits($text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim(mb_strtolower($text));
    }

    private const DEFINITE_PREFIXES = ['وال', 'بال', 'فال', 'كال', 'لل', 'ال'];

    private const SINGLE_PREFIXES = ['و', 'ب', 'ل', 'ف'];

    private const MIN_STEM = 3;

    private const MIN_STEM_AFTER_SINGLE = 4;

    public static function stripDefiniteArticle(string $word): string
    {
        foreach (self::DEFINITE_PREFIXES as $prefix) {
            if (str_starts_with($word, $prefix) && mb_strlen($word) - mb_strlen($prefix) >= self::MIN_STEM) {
                return mb_substr($word, mb_strlen($prefix));
            }
        }

        return $word;
    }

    public static function prefixVariants(string $word): array
    {
        $variants = [];
        $stripped = self::stripDefiniteArticle($word);

        if ($stripped !== $word) {
            $variants[] = $stripped;
        }

        if (in_array(mb_substr($stripped, 0, 1), self::SINGLE_PREFIXES, true)
            && mb_strlen($stripped) - 1 >= self::MIN_STEM_AFTER_SINGLE) {
            $variants[] = mb_substr($stripped, 1);
        }

        return array_values(array_unique($variants));
    }

    public static function withSearchVariants(string $normalized): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $extra = [];

        foreach (array_unique($words) as $word) {
            foreach (self::prefixVariants($word) as $variant) {
                $extra[$variant] = true;
            }
        }

        return $extra === [] ? $normalized : $normalized.' '.implode(' ', array_keys($extra));
    }

    public static function slug(?string $text, int $max = 80): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = self::toLatinDigits(self::stripMarks($text));
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        $text = trim($text, '-');

        if (mb_strlen($text) > $max) {
            $text = rtrim(mb_substr($text, 0, $max), '-');
        }

        return $text;
    }
}
