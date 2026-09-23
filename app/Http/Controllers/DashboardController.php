<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenewListing;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const TABS = ['pending', 'active', 'expired', 'rejected', 'sold'];

    public function __invoke(Request $request, RenewListing $renew): View
    {
        $user = $request->user();

        $tab = in_array($request->query('status'), self::TABS, true) ? (string) $request->query('status') : 'active';

        $counts = collect(self::TABS)->mapWithKeys(
            fn (string $status) => [$status => $this->tabQuery($user->listings(), $status)->count()],
        );

        if (! $request->has('status') && $counts['active'] === 0) {
            $tab = collect(self::TABS)->first(fn (string $status) => $counts[$status] > 0) ?? 'active';
        }

        $listings = $this->tabQuery($user->listings(), $tab)
            ->with(['category', 'governorate', 'city', 'media'])
            ->withCount([
                'events as phone_clicks_count' => fn (Builder $q) => $q->where('type', ListingEventType::PhoneClick->value),
                'events as whatsapp_clicks_count' => fn (Builder $q) => $q->where('type', ListingEventType::WhatsappClick->value),
                'favorites as favorites_count',
            ])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('dashboard', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
            'listings' => $listings,
            'renewable' => $listings->getCollection()
                ->filter(fn (Listing $listing) => $renew->canRenew($listing))
                ->pluck('id')
                ->flip(),
        ]);
    }

    private function tabQuery($relation, string $tab): Builder
    {
        $query = $relation->getQuery();

        return match ($tab) {
            'active' => $query->active(),
            'expired' => $query->expired(),
            default => $query->where('status', ListingStatus::from($tab)->value),
        };
    }
}
