<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdBanner;
use Illuminate\Http\RedirectResponse;

class AdBannerClickController extends Controller
{
    public function __invoke(AdBanner $adBanner): RedirectResponse
    {
        $live = AdBanner::query()->currentlyActive()->with('listing')->find($adBanner->getKey());

        if ($live === null || $live->resolvedUrl() === null) {
            return redirect()->route('home');
        }

        $live->increment('clicks');

        return redirect()->away($live->resolvedUrl());
    }
}
