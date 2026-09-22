<?php

declare(strict_types=1);

use App\Services\ArabicText;

it('folds Arabic letter variants', function (string $input, string $expected) {
    expect(ArabicText::normalize($input))->toBe($expected);
})->with([
    'alef with hamza above' => ['أحمد', 'احمد'],
    'alef with hamza below' => ['إسلام', 'اسلام'],
    'alef with madda' => ['آية', 'ايه'],
    'alef maksura to yeh' => ['مستشفى', 'مستشفي'],
    'teh marbuta to heh' => ['شقة', 'شقه'],
    'waw with hamza' => ['مؤسسة', 'موسسه'],
    'yeh with hamza' => ['رئيس', 'رييس'],
    'Persian yeh and kaf' => ['کیف', 'كيف'],
]);

it('strips diacritics and tatweel', function () {
    expect(ArabicText::normalize('مُحَمَّدٌ'))->toBe('محمد')
        ->and(ArabicText::normalize('العـــربية'))->toBe('العربيه');
});

it('converts Arabic-Indic digits to Latin digits', function () {
    expect(ArabicText::normalize('شقة ٣ غرف ٢٠٢٤'))->toBe('شقه 3 غرف 2024')
        ->and(ArabicText::toLatinDigits('۰۱۲۳۴۵۶۷۸۹'))->toBe('0123456789');
});

it('collapses whitespace and lowercases', function () {
    expect(ArabicText::normalize("  BMW   X5 \n\t 2020  "))->toBe('bmw x5 2020');
});

it('makes "شقة" and "شقه" identical after normalization', function () {
    expect(ArabicText::normalize('شقة للبيع'))->toBe(ArabicText::normalize('شقه للبيع'));
});

it('handles empty input', function () {
    expect(ArabicText::normalize(null))->toBe('')
        ->and(ArabicText::normalize(''))->toBe('')
        ->and(ArabicText::slug(null))->toBe('');
});

it('builds slugs that keep Arabic letters', function () {
    expect(ArabicText::slug('شقة للبيع في مدينة نصر!'))->toBe('شقة-للبيع-في-مدينة-نصر')
        ->and(ArabicText::slug('تويوتا كورولا 2020'))->toBe('تويوتا-كورولا-2020')
        ->and(ArabicText::slug('iPhone 15 Pro Max'))->toBe('iphone-15-pro-max')
        ->and(ArabicText::slug('سيارة ٢٠٢٠ - جديدة'))->toBe('سيارة-2020-جديدة')
        ->and(ArabicText::slug('مُحَمَّد'))->toBe('محمد');
});

it('trims slug separators and collapses repeated ones', function () {
    expect(ArabicText::slug('---شقة   ***  للبيع---'))->toBe('شقة-للبيع');
});

it('limits slugs to 80 characters without a trailing dash', function () {
    $slug = ArabicText::slug(str_repeat('كلمة ', 40));

    expect(mb_strlen($slug))->toBeLessThanOrEqual(80)
        ->and($slug)->not->toEndWith('-');
});

it('returns an empty slug when nothing usable is left', function () {
    expect(ArabicText::slug('!!! ??? ---'))->toBe('');
});

it('strips the definite article and the prepositions glued to it', function (string $word, string $expected) {
    expect(ArabicText::stripDefiniteArticle($word))->toBe($expected);
})->with([
    'ال' => ['الشقه', 'شقه'],
    'بال' => ['بالقاهره', 'قاهره'],
    'وال' => ['والسياره', 'سياره'],
    'فال' => ['فالبيت', 'بيت'],
    'كال' => ['كالعاده', 'عاده'],
    'لل' => ['للبيع', 'بيع'],
    'a word without an article is unchanged' => ['شقه', 'شقه'],
    'less than 3 letters would remain' => ['الف', 'الف'],
    'exactly 3 letters remain' => ['البيت', 'بيت'],
]);

it('lists the extra forms a word can be found by', function (string $word, array $expected) {
    expect(ArabicText::prefixVariants($word))->toBe($expected);
})->with([
    'article' => ['الشقه', ['شقه']],
    'article + preposition' => ['بالقاهره', ['قاهره']],
    'conjunction only' => ['وسياره', ['سياره']],
    'conjunction + article' => ['والسياره', ['سياره']],
    'a short remainder is not indexed after a single letter' => ['كبير', []],
    'a word that only looks prefixed' => ['بيت', []],
    'nothing to strip' => ['شقه', []],
    'digits' => ['2019', []],
]);

it('appends the article-less form of each word to the indexed text', function () {
    expect(ArabicText::withSearchVariants('شقه للبيع في القاهره'))
        ->toBe('شقه للبيع في القاهره بيع قاهره')
        ->and(ArabicText::withSearchVariants('شقه جميله'))->toBe('شقه جميله')
        ->and(ArabicText::withSearchVariants(''))->toBe('');
});
