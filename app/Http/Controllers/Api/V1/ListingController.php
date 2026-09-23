<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ListingResource;
use App\Models\Governorate;
use App\Models\Listing;
use App\Queries\ListingSearch;
use App\Services\CategoryTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->filled('category')
            ? CategoryTree::flatten()->firstWhere('slug', (string) $request->query('category'))
            : null;

        $governorate = $request->filled('governorate')
            ? Governorate::query()->where('slug', (string) $request->query('governorate'))->first()
            : null;

        $listings = ListingSearch::make($request->query(), $category, $governorate)
            ->paginate((int) config('classifieds.per_page'));

        return response()->json([
            'data' => ListingResource::collection($listings->load(['category', 'governorate', 'city', 'media'])),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
            ],
        ]);
    }

    public function show(Request $request, Listing $listing): JsonResponse
    {
        abort_unless(Listing::query()->visible()->whereKey($listing->id)->exists(), 404);

        $listing->load(['category', 'governorate', 'city', 'user', 'fieldValues.field', 'media']);

        if ($request->user()?->id !== $listing->user_id) {
            $listing->increment('views');
            $listing->events()->create(['type' => ListingEventType::View]);
        }

        return response()->json(['data' => new ListingResource($listing)]);
    }
}
