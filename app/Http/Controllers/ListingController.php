<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateListing;
use App\Actions\MarkListingSold;
use App\Actions\RenewListing;
use App\Actions\UpdateListing;
use App\Enums\FieldType;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Exceptions\ListingActionException;
use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Models\CategoryField;
use App\Models\Listing;
use App\Services\CategoryTree;
use App\Support\ListingFormPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListingController extends Controller
{
    /**
     * Public listing page (/ad/{id}/{slug}).
     *
     * Visibility: only live listings of non-banned owners are public. Everything else is a 404 for
     * visitors (expired listings: 410 Gone), except the owner and staff, who see it with a status banner.
     * The visibility check runs BEFORE the canonical redirect so the slug (= the title) of an
     * unpublished listing never leaks through a 301.
     */
    public function show(Request $request, Listing $listing, ?string $slug = null): View|RedirectResponse
    {
        $listing->load(['category', 'governorate', 'city', 'user', 'fieldValues', 'media']);

        $viewer = $request->user();
        $privileged = $viewer !== null
            && ! $viewer->is_banned
            && ($viewer->isStaff() || $viewer->id === $listing->user_id);

        $ownerBanned = (bool) $listing->user->is_banned;
        $publiclyVisible = ! $ownerBanned && $listing->status === ListingStatus::Active && ! $listing->isExpired();

        if (! $publiclyVisible && ! $privileged) {
            abort(! $ownerBanned && $listing->isExpired() ? 410 : 404);
        }

        if ($slug !== $listing->slug) {
            return redirect()->to($listing->url(), 301);
        }

        if ($publiclyVisible && $viewer?->id !== $listing->user_id) {
            $this->recordView($request, $listing);
        }

        $category = CategoryTree::find($listing->category_id) ?? $listing->category;

        $similar = Listing::query()
            ->visible()
            ->where('category_id', $listing->category_id)
            ->whereKeyNot($listing->id)
            ->with(['category', 'governorate', 'city', 'media'])
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        return view('listings.show', [
            'listing' => $listing,
            'publiclyVisible' => $publiclyVisible,
            'ancestors' => $listing->category->ancestorsAndSelf(),
            'fieldRows' => $this->fieldRows($listing),
            'images' => $this->images($listing),
            'similar' => $similar,
            'sellerListingsCount' => Listing::query()->visible()->where('user_id', $listing->user_id)->count(),
            'category' => $category,
        ]);
    }

    /**
     * Count a view once per listing per session and store it as an event.
     */
    private function recordView(Request $request, Listing $listing): void
    {
        $viewed = $request->session()->get('viewed_listings', []);

        if (in_array($listing->id, $viewed, true)) {
            return;
        }

        $request->session()->put('viewed_listings', [...$viewed, $listing->id]);

        $listing->increment('views');
        $listing->events()->create(['type' => ListingEventType::View]);
    }

    /**
     * Dynamic field values in the category's field order (parent fields first), ready to display.
     *
     * @return Collection<int, array{name: string, value: string}>
     */
    private function fieldRows(Listing $listing): Collection
    {
        $values = $listing->fieldValues->keyBy('category_field_id');

        return $listing->category->effectiveFields()
            ->map(function (CategoryField $field) use ($values) {
                $stored = $values->get($field->id)?->value;

                if ($stored === null) {
                    return null;
                }

                $display = match ($field->type) {
                    FieldType::Boolean => $stored === '1' ? __('app.yes') : __('app.no'),
                    default => $stored,
                };

                return ['name' => $field->name, 'value' => $field->unit ? $display.' '.$field->unit : $display];
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<array{thumb: string, medium: string, large: string}>
     */
    private function images(Listing $listing): array
    {
        // Conversions are queued: until one exists the original is served instead.
        $url = fn (Media $media, string $conversion) => $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();

        return $listing->getMedia(Listing::IMAGES)->map(fn (Media $media) => [
            'thumb' => $url($media, 'thumb'),
            'medium' => $url($media, 'medium'),
            'large' => $url($media, 'large'),
        ])->values()->all();
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Listing::class);

        return view('listings.form', [
            'listing' => null,
            'payload' => ListingFormPayload::make($request->user()),
        ]);
    }

    public function store(StoreListingRequest $request, CreateListing $create): RedirectResponse
    {
        $listing = $create(
            $request->user(),
            $request->listingData(),
            $request->file('images', []),
            $request->input('cover'),
        );

        return redirect()->route('dashboard')->with('success', $listing->status === ListingStatus::Active
            ? __('app.listing.created_active')
            : __('app.listing.created_pending'));
    }

    public function edit(Request $request, Listing $listing): View
    {
        $this->authorize('update', $listing);

        $listing->load(['media', 'fieldValues.field']);

        return view('listings.form', [
            'listing' => $listing,
            'payload' => ListingFormPayload::make($request->user(), $listing),
        ]);
    }

    public function update(UpdateListingRequest $request, Listing $listing, UpdateListing $update): RedirectResponse
    {
        $listing = $update(
            $listing,
            $request->listingData(),
            $request->file('images', []),
            (array) $request->input('remove_images', []),
            $request->input('cover'),
        );

        return redirect()->route('dashboard')->with('success', $listing->status === ListingStatus::Pending
            ? __('app.listing.updated_pending')
            : __('app.listing.updated'));
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        $this->authorize('delete', $listing);

        $listing->delete();

        return redirect()->route('dashboard')->with('success', __('app.listing.deleted'));
    }

    public function renew(Listing $listing, RenewListing $renew): RedirectResponse
    {
        $this->authorize('renew', $listing);

        try {
            $renew($listing);
        } catch (ListingActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('app.listing.renewed'));
    }

    public function sold(Listing $listing, MarkListingSold $markSold): RedirectResponse
    {
        $this->authorize('markSold', $listing);

        try {
            $markSold($listing);
        } catch (ListingActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('app.listing.marked_sold'));
    }
}
