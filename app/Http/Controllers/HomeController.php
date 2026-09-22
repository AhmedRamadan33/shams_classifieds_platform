<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\CategoryTree;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Home page sections are cached for 5 minutes. */
    private const CACHE_SECONDS = 300;

    public function __invoke(): View
    {
        $featured = Cache::remember('home.featured', self::CACHE_SECONDS, fn () => Listing::query()
            ->visible()
            ->featured()
            ->with(['category', 'governorate', 'city', 'media'])
            ->orderByDesc('featured_until')
            ->orderByDesc('id')
            ->limit(8)
            ->get());

        $latest = Cache::remember('home.latest', self::CACHE_SECONDS, fn () => Listing::query()
            ->visible()
            ->with(['category', 'governorate', 'city', 'media'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get());

        return view('home', [
            'categories' => CategoryTree::get(),
            'featured' => $featured,
            'latest' => $latest,
        ]);
    }
}
