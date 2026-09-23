<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InitiateFeaturedPayment;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeaturedPurchaseController extends Controller
{
    public function create(Listing $listing): View
    {
        $this->authorize('feature', $listing);

        return view('payments.packages', [
            'listing' => $listing,
            'packages' => Package::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, Listing $listing, InitiateFeaturedPayment $initiate): RedirectResponse
    {
        $this->authorize('feature', $listing);

        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $package = Package::query()->active()->findOrFail($data['package_id']);

        try {
            $redirect = $initiate($request->user(), $listing, $package);
        } catch (PaymentGatewayException $e) {
            report($e);

            return back()->with('error', __('app.payments.gateway_unavailable'));
        }

        return redirect()->away($redirect->url);
    }

    public function show(Payment $payment): View
    {
        abort_unless($payment->user_id === auth()->id(), 403);

        return view('payments.result', ['payment' => $payment->load(['listing', 'package', 'subscription.plan', 'adBanner'])]);
    }

    public function returnFromGateway(Request $request): RedirectResponse
    {
        $merchantOrderId = (string) $request->query('merchant_order_id', '');
        $id = (int) str($merchantOrderId)->after('payment-')->toString();

        $payment = $id > 0 ? Payment::find($id) : null;

        abort_unless($payment !== null && $payment->user_id === auth()->id(), 404);

        return redirect()->route('payments.show', $payment);
    }
}
