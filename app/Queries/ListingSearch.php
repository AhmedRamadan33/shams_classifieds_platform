<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FieldType;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Governorate;
use App\Models\Listing;
use App\Services\ArabicText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Builds the public listing query (category pages, search results) from request input.
 *
 * Recognised input: q, city, price_min, price_max, price_type, sort, and f[key] / f[key][min|max]
 * for the category's filterable fields. Unknown keys and invalid values are ignored, never errors.
 */
final class ListingSearch
{
    public const SORTS = ['newest', 'price_asc', 'price_desc'];

    /** Terms shorter than this cannot be matched by an InnoDB FULLTEXT index (innodb_ft_min_token_size). */
    private const FULLTEXT_MIN_LENGTH = 3;

    private const MAX_TERMS = 8;

    /**
     * How many candidate ids to take from Meilisearch before the normal SQL filters (visibility,
     * category, price, fields) narrow them further. Bounded so an extremely broad query can't build
     * an unbounded whereIn(); a query this wide is not usefully "searched" anyway.
     */
    private const MEILISEARCH_CANDIDATES = 500;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        private readonly array $input,
        private readonly ?Category $category = null,
        private readonly ?Governorate $governorate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function make(array $input, ?Category $category = null, ?Governorate $governorate = null): self
    {
        return new self($input, $category, $governorate);
    }

    /**
     * Fields of the category that may be used as filters (own and inherited).
     *
     * @return Collection<int, CategoryField>
     */
    public function filterableFields(): Collection
    {
        return $this->category?->effectiveFields()->where('is_filterable', true)->values() ?? collect();
    }

    public function sort(): string
    {
        $sort = $this->input['sort'] ?? 'newest';

        return in_array($sort, self::SORTS, true) ? $sort : 'newest';
    }

    public function query(): Builder
    {
        $query = Listing::query()
            ->visible()
            ->with(['category', 'governorate', 'city', 'media']);

        if ($this->category !== null) {
            $query->whereIn('listings.category_id', $this->category->descendantIds(includeSelf: true));
        }

        if ($this->governorate !== null) {
            $query->where('listings.governorate_id', $this->governorate->id);
        }

        if (($city = $this->intInput('city')) !== null) {
            $query->where('listings.city_id', $city);
        }

        $this->applyPrice($query);
        $this->applyFieldFilters($query);
        $this->applySearch($query);
        $this->applySort($query);

        return $query;
    }

    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return $this->query()
            ->paginate($perPage ?? (int) config('classifieds.per_page'))
            ->withQueryString();
    }

    // ------------------------------------------------------------- filters

    private function applyPrice(Builder $query): void
    {
        if (($min = $this->numberInput($this->input['price_min'] ?? null)) !== null) {
            $query->where('listings.price', '>=', $min);
        }

        if (($max = $this->numberInput($this->input['price_max'] ?? null)) !== null) {
            $query->where('listings.price', '<=', $max);
        }

        $type = PriceType::tryFrom((string) ($this->input['price_type'] ?? ''));

        if ($type !== null) {
            $query->where('listings.price_type', $type->value);
        }
    }

    /**
     * f[key]=value for select/text/boolean fields, f[key][min|max] for numbers. Only fields flagged
     * "filterable" in the category's effective fields are honoured.
     */
    private function applyFieldFilters(Builder $query): void
    {
        $requested = $this->input['f'] ?? null;

        if (! is_array($requested) || $this->category === null) {
            return;
        }

        $fields = $this->filterableFields()->keyBy('key');

        foreach ($requested as $key => $value) {
            $field = $fields->get((string) $key);

            if ($field === null) {
                continue;
            }

            match ($field->type) {
                FieldType::Number => $this->applyNumberFilter($query, $field, $value),
                FieldType::Boolean => $this->applyEqualsFilter($query, $field, in_array((string) $value, ['0', '1'], true) ? (string) $value : null),
                default => $this->applyEqualsFilter($query, $field, is_scalar($value) ? trim((string) $value) : null),
            };
        }
    }

    private function applyEqualsFilter(Builder $query, CategoryField $field, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query->whereExists(fn (QueryBuilder $sub) => $sub
            ->selectRaw('1')
            ->from('listing_field_values as lfv')
            ->whereColumn('lfv.listing_id', 'listings.id')
            ->where('lfv.category_field_id', $field->id)
            ->where('lfv.value', $value));
    }

    private function applyNumberFilter(Builder $query, CategoryField $field, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $min = $this->numberInput($value['min'] ?? null);
        $max = $this->numberInput($value['max'] ?? null);

        if ($min === null && $max === null) {
            return;
        }

        $query->whereExists(function (QueryBuilder $sub) use ($field, $min, $max): void {
            $sub->selectRaw('1')
                ->from('listing_field_values as lfv')
                ->whereColumn('lfv.listing_id', 'listings.id')
                ->where('lfv.category_field_id', $field->id);

            // Values are stored as strings; compare them numerically.
            if ($min !== null) {
                $sub->whereRaw('CAST(lfv.value AS DECIMAL(20,4)) >= ?', [$min]);
            }

            if ($max !== null) {
                $sub->whereRaw('CAST(lfv.value AS DECIMAL(20,4)) <= ?', [$max]);
            }
        });
    }

    // -------------------------------------------------------------- search

    /**
     * Arabic-normalized text search. By default (SCOUT_DRIVER unset, the plan's MySQL-only setup),
     * terms of 3+ characters use the FULLTEXT index in boolean mode (each as +term*, so prefixes
     * match); shorter terms fall back to LIKE because InnoDB ignores tokens below
     * innodb_ft_min_token_size. When SCOUT_DRIVER=meilisearch is configured, Meilisearch's
     * typo-tolerant index replaces this step instead (see meilisearchIds()).
     */
    private function applySearch(Builder $query): void
    {
        $terms = self::terms((string) ($this->input['q'] ?? ''));

        if ($terms === []) {
            return;
        }

        if (config('scout.driver') === 'meilisearch') {
            $query->whereIn('listings.id', self::meilisearchIds($terms));

            return;
        }

        $long = array_filter($terms, fn (string $term) => mb_strlen($term) >= self::FULLTEXT_MIN_LENGTH);
        $short = array_filter($terms, fn (string $term) => mb_strlen($term) < self::FULLTEXT_MIN_LENGTH);

        if ($long !== []) {
            $boolean = implode(' ', array_map(self::booleanClause(...), $long));
            $query->whereRaw('MATCH(listings.search_text) AGAINST (? IN BOOLEAN MODE)', [$boolean]);
        }

        foreach ($short as $term) {
            $query->where('listings.search_text', 'like', '%'.self::escapeLike($term).'%');
        }
    }

    /**
     * Candidate ids from the Meilisearch index, most relevant first. This is deliberately the only
     * thing Meilisearch decides: every other filter in query() (visibility, category, price, dynamic
     * fields) still runs as a normal SQL WHERE against these ids, exactly as it would against the
     * FULLTEXT path above, so Meilisearch can only narrow results, never leak a hidden listing.
     *
     * @param  list<string>  $terms
     * @return Collection<int, int>
     */
    private static function meilisearchIds(array $terms): Collection
    {
        return Listing::search(implode(' ', $terms))->take(self::MEILISEARCH_CANDIDATES)->keys();
    }

    /**
     * «شقه» → +شقه*; «الشقه» → +(الشقه* شقه*), so a search with the definite article also finds ads
     * that write the noun without it (the index holds both forms, see ArabicText::withSearchVariants).
     */
    private static function booleanClause(string $term): string
    {
        $stripped = ArabicText::stripDefiniteArticle($term);

        return $stripped === $term ? '+'.$term.'*' : '+('.$term.'* '.$stripped.'*)';
    }

    /**
     * Normalized, de-duplicated search terms. Anything that is not a letter or digit is dropped,
     * which also removes the boolean-mode operators (+ - < > ( ) ~ * " @).
     *
     * @return list<string>
     */
    public static function terms(string $q): array
    {
        $normalized = ArabicText::normalize(mb_substr($q, 0, 200));
        $terms = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($terms)), 0, self::MAX_TERMS);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    // ---------------------------------------------------------------- sort

    /**
     * Featured listings always come first; then the chosen sort; the id keeps the order stable.
     */
    private function applySort(Builder $query): void
    {
        $query->orderByRaw(
            'CASE WHEN listings.featured_until IS NOT NULL AND listings.featured_until > ? THEN 1 ELSE 0 END DESC',
            [now()],
        );

        match ($this->sort()) {
            // Free listings are the cheapest; "call for price" (no amount) goes last.
            'price_asc' => $query->orderByRaw("CASE WHEN listings.price_type = 'free' THEN 0 WHEN listings.price IS NULL THEN 1e15 ELSE listings.price END ASC"),
            // Descending: real prices first, then free listings, and "call for price" last.
            'price_desc' => $query->orderByRaw("CASE WHEN listings.price_type = 'contact' THEN -2 WHEN listings.price IS NULL THEN -1 ELSE listings.price END DESC"),
            default => $query->orderByDesc('listings.published_at'),
        };

        $query->orderByDesc('listings.id');
    }

    // ------------------------------------------------------------- helpers

    private function intInput(string $key): ?int
    {
        $value = $this->input[$key] ?? null;

        return is_scalar($value) && ctype_digit((string) $value) ? (int) $value : null;
    }

    /**
     * Accepts Latin or Arabic digits and thousands separators; returns null for anything else.
     */
    private function numberInput(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $clean = str_replace(['٫', '٬', ',', ' '], ['.', '', '', ''], ArabicText::toLatinDigits((string) $value));

        return $clean !== '' && is_numeric($clean) && (float) $clean >= 0 ? $clean : null;
    }
}
