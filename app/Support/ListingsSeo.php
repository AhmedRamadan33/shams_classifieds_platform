<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Robots and canonical rules for listing collections (category and search pages).
 *
 *  - filtered, sorted or searched URLs: noindex,follow, canonical to the base URL (no query string)
 *  - page 2+ of an unfiltered list: indexable with a self canonical (?page=N)
 *  - category + governorate pages: indexable only when they have at least one active listing
 *  - pages past the last one are never indexed
 */
final class ListingsSeo
{
    /**
     * @return array{robots: string, canonical: string}
     */
    public static function for(
        Request $request,
        string $baseUrl,
        LengthAwarePaginator $listings,
        bool $requiresListings = false,
        bool $neverIndex = false,
    ): array {
        $refined = collect($request->query())
            ->except('page')
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->isNotEmpty();

        if ($neverIndex || $refined) {
            return ['robots' => 'noindex,follow', 'canonical' => $baseUrl];
        }

        $page = $listings->currentPage();

        if (($requiresListings && $listings->total() === 0) || ($page > 1 && $listings->isEmpty())) {
            return ['robots' => 'noindex,follow', 'canonical' => $baseUrl];
        }

        return [
            'robots' => 'index,follow',
            'canonical' => $page > 1 ? $baseUrl.'?page='.$page : $baseUrl,
        ];
    }
}
