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

final class ListingSearch
{
    public const SORTS = ['newest', 'price_asc', 'price_desc'];

    private const FULLTEXT_MIN_LENGTH = 3;

    private const MAX_TERMS = 8;

    private const MEILISEARCH_CANDIDATES = 500;

    public function __construct(
        private readonly array $input,
        private readonly ?Category $category = null,
        private readonly ?Governorate $governorate = null,
    ) {}

    public static function make(array $input, ?Category $category = null, ?Governorate $governorate = null): self
    {
        return new self($input, $category, $governorate);
    }

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

            if ($min !== null) {
                $sub->whereRaw('CAST(lfv.value AS DECIMAL(20,4)) >= ?', [$min]);
            }

            if ($max !== null) {
                $sub->whereRaw('CAST(lfv.value AS DECIMAL(20,4)) <= ?', [$max]);
            }
        });
    }

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

    private static function meilisearchIds(array $terms): Collection
    {
        return Listing::search(implode(' ', $terms))->take(self::MEILISEARCH_CANDIDATES)->keys();
    }

    private static function booleanClause(string $term): string
    {
        $stripped = ArabicText::stripDefiniteArticle($term);

        return $stripped === $term ? '+'.$term.'*' : '+('.$term.'* '.$stripped.'*)';
    }

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

    private function applySort(Builder $query): void
    {
        $query->orderByRaw(
            'CASE WHEN listings.featured_until IS NOT NULL AND listings.featured_until > ? THEN 1 ELSE 0 END DESC',
            [now()],
        );

        match ($this->sort()) {
            'price_asc' => $query->orderByRaw("CASE WHEN listings.price_type = 'free' THEN 0 WHEN listings.price IS NULL THEN 1e15 ELSE listings.price END ASC"),
            'price_desc' => $query->orderByRaw("CASE WHEN listings.price_type = 'contact' THEN -2 WHEN listings.price IS NULL THEN -1 ELSE listings.price END DESC"),
            default => $query->orderByDesc('listings.published_at'),
        };

        $query->orderByDesc('listings.id');
    }

    private function intInput(string $key): ?int
    {
        $value = $this->input[$key] ?? null;

        return is_scalar($value) && ctype_digit((string) $value) ? (int) $value : null;
    }

    private function numberInput(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $clean = str_replace(['٫', '٬', ',', ' '], ['.', '', '', ''], ArabicText::toLatinDigits((string) $value));

        return $clean !== '' && is_numeric($clean) && (float) $clean >= 0 ? $clean : null;
    }
}
