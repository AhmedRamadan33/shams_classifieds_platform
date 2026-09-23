<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdBanner;
use Illuminate\Http\RedirectResponse;

class AdBannerClickController extends Controller
{
    public function __invoke(AdBanner $adBanner): RedirectResponse
    {
        $adBanner->increment('clicks');

        return redirect()->away($adBanner->target_url);
    }
}
