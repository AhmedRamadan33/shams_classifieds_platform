<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerController extends Controller
{
    /**
     * /seller/{user}: the seller's public listings and reviews.
     */
    public function __invoke(Request $request, User $user): View
    {
        abort_if($user->is_banned, 404);

        $listings = Listing::query()
            ->visible()
            ->where('user_id', $user->id)
            ->with(['category', 'governorate', 'city', 'media'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('classifieds.per_page'));

        $reviews = $user->reviewsReceived()->visible()->with('reviewer')->latest()->limit(20)->get();

        $myReview = $request->user() !== null
            ? Review::query()->where('reviewer_id', $request->user()->id)->where('seller_id', $user->id)->first()
            : null;

        return view('sellers.show', [
            'seller' => $user,
            'listings' => $listings,
            'reviews' => $reviews,
            'myReview' => $myReview,
        ]);
    }
}
