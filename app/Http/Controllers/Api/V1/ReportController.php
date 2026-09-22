<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportListingRequest;
use App\Models\Listing;
use App\Models\Report;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * POST /api/v1/listings/{listing}/reports: one report per user per listing, never your own.
     */
    public function store(ReportListingRequest $request, Listing $listing): JsonResponse
    {
        $user = $request->user();

        abort_unless(Listing::query()->visible()->whereKey($listing->id)->exists(), 404);

        if ($listing->user_id === $user->id) {
            return response()->json(['message' => __('app.report.own_listing')], 422);
        }

        $report = Report::query()->firstOrCreate(
            ['listing_id' => $listing->id, 'user_id' => $user->id],
            ['reason' => $request->validated('reason'), 'note' => $request->validated('note')],
        );

        return response()->json(
            ['message' => $report->wasRecentlyCreated ? __('app.report.thanks') : __('app.report.already')],
            $report->wasRecentlyCreated ? 201 : 409,
        );
    }
}
