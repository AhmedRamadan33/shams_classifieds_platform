<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InitiateAdBannerPayment;
use App\Models\AdBanner;
use App\Models\AdPackage;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdBannerPurchaseController extends Controller
{
    public function create(AdBanner $adBanner): View
    {
        $this->authorize('purchase', $adBanner);

        return view('ad-banners.purchase', [
            'adBanner' => $adBanner,
            'packages' => AdPackage::query()->active()->forPlacement($adBanner->placement)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, AdBanner $adBanner, InitiateAdBannerPayment $initiate): RedirectResponse
    {
        $this->authorize('purchase', $adBanner);

        $data = $request->validate([
            'ad_package_id' => ['required', 'integer', 'exists:ad_packages,id'],
        ]);

        $package = AdPackage::query()->active()->forPlacement($adBanner->placement)->findOrFail($data['ad_package_id']);

        try {
            $redirect = $initiate($request->user(), $adBanner, $package);
        } catch (PaymentGatewayException $e) {
            report($e);

            return back()->with('error', __('app.payments.gateway_unavailable'));
        }

        return redirect()->away($redirect->url);
    }
}
