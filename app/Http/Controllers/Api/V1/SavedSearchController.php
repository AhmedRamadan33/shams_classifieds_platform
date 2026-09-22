<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSearchRequest;
use App\Http\Resources\Api\SavedSearchResource;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $searches = $request->user()->savedSearches()->orderByDesc('created_at')->orderByDesc('id')->get();
        $searches->each(fn (SavedSearch $search) => $search->setAttribute('current_count', $search->listingSearch()->query()->count()));

        return response()->json(['data' => SavedSearchResource::collection($searches)]);
    }

    public function store(SaveSearchRequest $request): JsonResponse
    {
        $search = $request->user()->savedSearches()->create([
            'name' => $request->validated('name'),
            'category_slug' => $request->validated('category_slug'),
            'governorate_slug' => $request->validated('governorate_slug'),
            'filters' => $request->filters(),
            'notify' => $request->boolean('notify'),
        ]);

        return response()->json(['data' => new SavedSearchResource($search)], 201);
    }

    public function update(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        $this->authorize('update', $savedSearch);

        $savedSearch->update(['notify' => $request->boolean('notify')]);

        return response()->json(['data' => new SavedSearchResource($savedSearch)]);
    }

    public function destroy(SavedSearch $savedSearch): JsonResponse
    {
        $this->authorize('delete', $savedSearch);

        $savedSearch->delete();

        return response()->json(['message' => __('app.saved_searches.deleted')]);
    }
}
