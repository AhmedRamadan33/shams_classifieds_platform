<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Governorate;
use App\Queries\ListingSearch;
use App\Services\CategoryTree;
use App\Support\ListingsSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * /category/{category} and /category/{category}/{governorate}
     */
    public function show(Request $request, Category $category, ?Governorate $governorate = null): View|RedirectResponse
    {
        // Only categories reachable in the active tree (the category and all its ancestors are active).
        $node = CategoryTree::find($category->id);
        abort_if($node === null, 404);

        // The filter form sends ?governorate=slug; the canonical, indexable form is the path URL.
        if ($governorate === null && $request->filled('governorate')) {
            $chosen = Governorate::query()->where('slug', (string) $request->query('governorate'))->first();

            if ($chosen !== null) {
                return redirect()->route('categories.governorate', [
                    'category' => $category->slug,
                    'governorate' => $chosen->slug,
                    ...$request->except(['governorate', 'page', 'category']),
                ]);
            }
        }

        $search = ListingSearch::make($request->query(), $node, $governorate);
        $listings = $search->paginate();

        $baseUrl = $governorate
            ? route('categories.governorate', [$category->slug, $governorate->slug])
            : route('categories.show', $category->slug);

        return view('listings.index', [
            // A category + governorate page is only indexable when it actually has listings.
            'seo' => ListingsSeo::for($request, $baseUrl, $listings, requiresListings: $governorate !== null),
            'heading' => $governorate
                ? __('app.browse.category_in', ['category' => $node->name, 'governorate' => $governorate->name])
                : $node->name,
            'category' => $node,
            'governorate' => $governorate,
            'listings' => $listings,
            'search' => $search,
            'children' => $node->children,
            'ancestors' => $node->ancestorsAndSelf(),
            'formAction' => $baseUrl,
            'hidden' => [],
            'isSearch' => false,
        ]);
    }
}
