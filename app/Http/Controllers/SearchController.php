<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Governorate;
use App\Queries\ListingSearch;
use App\Services\CategoryTree;
use App\Support\ListingsSeo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * /search?q=&category=&governorate=&...
     */
    public function __invoke(Request $request): View
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 100, '');

        $category = $request->filled('category')
            ? CategoryTree::flatten()->firstWhere('slug', (string) $request->query('category'))
            : null;

        $governorate = $request->filled('governorate')
            ? Governorate::query()->where('slug', (string) $request->query('governorate'))->first()
            : null;

        $search = ListingSearch::make([...$request->query(), 'q' => $q], $category, $governorate);
        $listings = $search->paginate();

        return view('listings.index', [
            // Search results are never indexed; the canonical is always the bare /search URL.
            'seo' => ListingsSeo::for($request, route('search'), $listings, neverIndex: true),
            'heading' => $q !== '' ? __('app.browse.search_results_for', ['q' => $q]) : __('app.browse.all_listings'),
            'category' => $category,
            'governorate' => $governorate,
            'listings' => $listings,
            'search' => $search,
            'children' => collect(),
            'ancestors' => $category?->ancestorsAndSelf() ?? collect(),
            'formAction' => route('search'),
            'hidden' => array_filter(['q' => $q, 'category' => $category?->slug]),
            'isSearch' => true,
            'q' => $q,
        ]);
    }
}
