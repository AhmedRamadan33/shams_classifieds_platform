<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveReviewRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    /**
     * POST /seller/{user}/reviews: leaves a review, or updates the reviewer's existing one for this
     * seller (one per reviewer per seller — see the unique index).
     */
    public function store(SaveReviewRequest $request, User $seller): RedirectResponse
    {
        $reviewer = $request->user();

        if ($seller->id === $reviewer->id) {
            return back()->with('error', __('app.reviews.own_profile'));
        }

        Review::query()->updateOrCreate(
            ['reviewer_id' => $reviewer->id, 'seller_id' => $seller->id],
            [
                'listing_id' => $request->validated('listing_id'),
                'rating' => $request->validated('rating'),
                'comment' => $request->validated('comment'),
            ],
        );

        return back()->with('success', __('app.reviews.saved'));
    }

    public function destroy(Review $review): RedirectResponse
    {
        abort_unless($review->reviewer_id === auth()->id(), 403);

        $review->delete();

        return back()->with('success', __('app.reviews.deleted'));
    }
}
