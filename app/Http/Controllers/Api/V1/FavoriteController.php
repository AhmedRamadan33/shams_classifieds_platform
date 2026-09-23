<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ListingResource;
use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $listings = Listing::query()
            ->visible()
            ->whereHas('favorites', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['category', 'governorate', 'city', 'media'])
            ->orderByDesc(
                Favorite::query()->select('created_at')
                    ->whereColumn('favorites.listing_id', 'listings.id')
                    ->where('favorites.user_id', $request->user()->id)
                    ->limit(1),
            )
            ->paginate((int) config('classifieds.per_page'));

        return response()->json(['data' => ListingResource::collection($listings)]);
    }

    public function toggle(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        $existing = Favorite::query()->where('user_id', $user->id)->where('listing_id', $listing->id)->first();
        $wanted = $request->has('favorite') ? $request->boolean('favorite') : $existing === null;

        if ($wanted && $existing === null) {
            abort_unless(Listing::query()->visible()->whereKey($listing->id)->exists(), 404);

            Favorite::query()->firstOrCreate(['user_id' => $user->id, 'listing_id' => $listing->id]);
        }

        if (! $wanted && $existing !== null) {
            $existing->delete();
        }

        return response()->json(['favorited' => $wanted]);
    }
}
