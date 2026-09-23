<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class ListingsSeo
{
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
