<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ListingEventType;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingContactController extends Controller
{
    public function __invoke(Request $request, Listing $listing): JsonResponse
    {
        abort_unless(Listing::query()->visible()->whereKey($listing->id)->exists(), 404);

        $type = $request->input('channel') === 'whatsapp'
            ? ListingEventType::WhatsappClick
            : ListingEventType::PhoneClick;

        $listing->events()->create(['type' => $type]);

        return response()->json([
            'phone' => $listing->phone,
            'whatsapp_url' => $listing->whatsappUrl(),
        ]);
    }
}
