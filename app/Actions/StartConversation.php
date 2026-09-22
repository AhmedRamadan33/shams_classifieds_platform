<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\MessagingException;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "راسل المعلن": finds the buyer's existing conversation about this listing, or opens a new one.
 * The listing must currently be live and the buyer cannot be the listing's own owner.
 */
final class StartConversation
{
    public function __invoke(User $buyer, Listing $listing): Conversation
    {
        if ($listing->user_id === $buyer->id) {
            throw MessagingException::ownListing();
        }

        if (! $listing->isPubliclyListed()) {
            throw MessagingException::listingUnavailable();
        }

        return DB::transaction(fn () => Conversation::query()->firstOrCreate(
            ['listing_id' => $listing->id, 'buyer_id' => $buyer->id],
            ['seller_id' => $listing->user_id],
        ));
    }
}
