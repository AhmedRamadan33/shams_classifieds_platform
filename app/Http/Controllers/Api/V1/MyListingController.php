<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateListing;
use App\Actions\MarkListingSold;
use App\Actions\RenewListing;
use App\Actions\UpdateListing;
use App\Exceptions\ListingActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own listings (auth:sanctum + phone.verified, see routes/api.php). Reuses the
 * exact same form requests/actions/policies as the website (App\Http\Requests\Store/UpdateListingRequest,
 * App\Actions\CreateListing/UpdateListing), so validation and business rules never diverge between them.
 */
class MyListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $listings = $request->user()->listings()
            ->with(['category', 'governorate', 'city', 'media'])
            ->latest('id')
            ->paginate((int) config('classifieds.per_page'));

        return response()->json([
            'data' => ListingResource::collection($listings),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'total' => $listings->total(),
            ],
        ]);
    }

    public function show(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('view', $listing);

        $listing->load(['category', 'governorate', 'city', 'fieldValues.field', 'media']);

        return response()->json(['data' => new ListingResource($listing)]);
    }

    public function store(StoreListingRequest $request, CreateListing $create): JsonResponse
    {
        $listing = $create($request->user(), $request->listingData(), $request->file('images', []), $request->input('cover'));

        return response()->json(['data' => new ListingResource($listing->load(['category', 'governorate', 'city', 'media']))], 201);
    }

    public function update(UpdateListingRequest $request, Listing $listing, UpdateListing $update): JsonResponse
    {
        $listing = $update(
            $listing,
            $request->listingData(),
            $request->file('images', []),
            (array) $request->input('remove_images', []),
            $request->input('cover'),
        );

        return response()->json(['data' => new ListingResource($listing->load(['category', 'governorate', 'city', 'media']))]);
    }

    public function destroy(Listing $listing): JsonResponse
    {
        $this->authorize('delete', $listing);

        $listing->delete();

        return response()->json(['message' => __('app.listing.deleted')]);
    }

    public function renew(Listing $listing, RenewListing $renew): JsonResponse
    {
        $this->authorize('renew', $listing);

        try {
            $renew($listing);
        } catch (ListingActionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new ListingResource($listing)]);
    }

    public function sold(Listing $listing, MarkListingSold $markSold): JsonResponse
    {
        $this->authorize('markSold', $listing);

        try {
            $markSold($listing);
        } catch (ListingActionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new ListingResource($listing)]);
    }
}
