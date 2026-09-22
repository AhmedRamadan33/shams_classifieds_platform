<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportListingRequest;
use App\Models\Listing;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    /**
     * POST /ad/{listing}/report: one report per user per listing, on public listings, never on
     * your own listing.
     */
    public function store(ReportListingRequest $request, Listing $listing): RedirectResponse
    {
        $user = $request->user();

        abort_unless(Listing::query()->visible()->whereKey($listing->id)->exists(), 404);

        if ($listing->user_id === $user->id) {
            return back()->with('error', __('app.report.own_listing'));
        }

        $report = Report::query()->firstOrCreate(
            ['listing_id' => $listing->id, 'user_id' => $user->id],
            ['reason' => $request->validated('reason'), 'note' => $request->validated('note')],
        );

        return back()->with(
            $report->wasRecentlyCreated ? 'success' : 'error',
            $report->wasRecentlyCreated ? __('app.report.thanks') : __('app.report.already'),
        );
    }
}
