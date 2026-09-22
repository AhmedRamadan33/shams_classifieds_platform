<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Arabic text helpers used by search indexing, slugs and phone/number input.
 */
final class ArabicText
{
    /** Arabic-Indic (٠-٩) and Eastern Arabic-Indic / Persian (۰-۹) digits mapped to Latin. */
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** Letter variants folded to a single canonical letter. */
    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ی' => 'ي',
        'ة' => 'ه',
        'ؤ' => 'و',
        'ک' => 'ك',
    ];

    /**
     * Convert Arabic-Indic digits to Latin digits, leaving everything else untouched.
     */
    public static function toLatinDigits(string $text): string
    {
        return strtr($text, self::DIGITS);
    }

    /**
     * Remove diacritics (tashkeel), Quranic marks and tatweel.
     */
    public static function stripMarks(string $text): string
    {
        return (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text);
    }

    /**
     * Normalize text for indexing and matching: strip diacritics and tatweel, fold letter
     * variants (أ إ آ → ا, ى → ي, ة → ه, ؤ → و, ئ → ي), convert digits to Latin,
     * collapse whitespace and lowercase.
     */
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

    /**
     * The definite article and the prefixes glued to it («ال»، «بال»، «وال»، «فال»، «كال»، «لل»),
     * longest first. Applied to already normalized words.
     */
    private const DEFINITE_PREFIXES = ['وال', 'بال', 'فال', 'كال', 'لل', 'ال'];

    /** One-letter conjunctions/prepositions glued to a word: و ب ل ف. */
    private const SINGLE_PREFIXES = ['و', 'ب', 'ل', 'ف'];

    /** A word must keep at least this many letters after a prefix is removed. */
    private const MIN_STEM = 3;

    /** After removing a single letter, require a longer stem: «كبير» must not index «بير». */
    private const MIN_STEM_AFTER_SINGLE = 4;

    /**
     * Remove the definite article (and the preposition glued to it) from a normalized word:
     * «الشقه» → «شقه»، «بالقاهره» → «قاهره»، «للبيع» → «بيع». Returns the word unchanged when
     * fewer than three letters would be left.
     */
    public static function stripDefiniteArticle(string $word): string
    {
        foreach (self::DEFINITE_PREFIXES as $prefix) {
            if (str_starts_with($word, $prefix) && mb_strlen($word) - mb_strlen($prefix) >= self::MIN_STEM) {
                return mb_substr($word, mb_strlen($prefix));
            }
        }

        return $word;
    }

    /**
     * The extra forms a word can be searched by, so that «شقه» finds an ad that says «الشقه»
     * and «القاهره» finds «بالقاهره»: the word without its definite article, and additionally
     * without a leading و/ب/ل/ف («وسياره» → «سياره»). Empty when the word has no such prefix.
     *
     * @return list<string>
     */
    public static function prefixVariants(string $word): array
    {
        $variants = [];
        $stripped = self::stripDefiniteArticle($word);

        if ($stripped !== $word) {
            $variants[] = $stripped;
        }

        // «وسياره» → «سياره»; also «والفيوم» → «فيوم» → (too short, kept as is).
        if (in_array(mb_substr($stripped, 0, 1), self::SINGLE_PREFIXES, true)
            && mb_strlen($stripped) - 1 >= self::MIN_STEM_AFTER_SINGLE) {
            $variants[] = mb_substr($stripped, 1);
        }

        return array_values(array_unique($variants));
    }

    /**
     * Normalized text followed by the prefix-stripped variant of each of its words. Used for the
     * FULLTEXT column: MySQL does no Arabic stemming, so we index both forms ourselves.
     */
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

    /**
     * URL slug that keeps Arabic letters: other characters become "-", max $max characters.
     */
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
