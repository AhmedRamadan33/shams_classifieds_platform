<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveStoreRequest;
use App\Models\Listing;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function edit(Request $request): View
    {
        return view('stores.edit', ['store' => $request->user()->store]);
    }

    public function save(SaveStoreRequest $request): RedirectResponse
    {
        $request->user()->store()->updateOrCreate([], $request->validated());

        return redirect()->route('store.edit')->with('success', __('app.stores.saved'));
    }

    public function show(Store $store): View
    {
        abort_if($store->user->is_banned, 404);

        $listings = Listing::query()
            ->visible()
            ->where('user_id', $store->user_id)
            ->with(['category', 'governorate', 'city', 'media'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('classifieds.per_page'));

        return view('stores.show', [
            'store' => $store,
            'listings' => $listings,
        ]);
    }
}
