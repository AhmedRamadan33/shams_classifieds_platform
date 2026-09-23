<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdBanner;
use Illuminate\Http\RedirectResponse;

class AdBannerClickController extends Controller
{
    public function __invoke(AdBanner $adBanner): RedirectResponse
    {
        if (! AdBanner::query()->currentlyActive()->whereKey($adBanner->getKey())->exists()) {
            return redirect()->route('home');
        }

        $adBanner->increment('clicks');

        return redirect()->away($adBanner->target_url);
    }
}
