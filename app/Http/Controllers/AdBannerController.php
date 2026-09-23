<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateAdBanner;
use App\Enums\AdPlacement;
use App\Http\Requests\StoreAdBannerRequest;
use App\Models\AdBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdBannerController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', AdBanner::class);

        return view('ad-banners.create', ['placements' => AdPlacement::cases()]);
    }

    public function store(StoreAdBannerRequest $request, CreateAdBanner $create): RedirectResponse
    {
        $data = $request->validated();

        $create(
            $request->user(),
            AdPlacement::from($data['placement']),
            $data['target_url'],
            $data['title'] ?? null,
            $request->file('image'),
        );

        return redirect()->route('ad-banners.index')->with('success', __('app.ad_banners.submitted'));
    }

    public function index(Request $request): View
    {
        $banners = $request->user()->adBanners()
            ->with('adPackage')
            ->latest('id')
            ->paginate(10);

        return view('ad-banners.index', ['banners' => $banners]);
    }
}
