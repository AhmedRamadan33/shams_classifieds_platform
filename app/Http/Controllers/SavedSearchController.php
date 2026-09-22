<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveSearchRequest;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedSearchController extends Controller
{
    public function index(Request $request): View
    {
        $searches = $request->user()->savedSearches()->orderByDesc('created_at')->orderByDesc('id')->get();

        $searches->each(fn (SavedSearch $search) => $search->setAttribute(
            'current_count',
            $search->listingSearch()->query()->count(),
        ));

        return view('saved-searches.index', ['searches' => $searches]);
    }

    public function store(SaveSearchRequest $request): RedirectResponse
    {
        $request->user()->savedSearches()->create([
            'name' => $request->validated('name'),
            'category_slug' => $request->validated('category_slug'),
            'governorate_slug' => $request->validated('governorate_slug'),
            'filters' => $request->filters(),
            'notify' => $request->boolean('notify'),
        ]);

        return back()->with('success', __('app.saved_searches.saved'));
    }

    public function update(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        $this->authorize('update', $savedSearch);

        $savedSearch->update(['notify' => $request->boolean('notify')]);

        return back()->with('success', __('app.saved_searches.updated'));
    }

    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        $this->authorize('delete', $savedSearch);

        $savedSearch->delete();

        return back()->with('success', __('app.saved_searches.deleted'));
    }
}
