<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InitiateSubscriptionPayment;
use App\Models\Plan;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buying a store subscription (higher daily listing limit; see App\Services\ListingLimits and
 * App\Models\Store::isActive()). Shares the payment gateway, webhook and result page with
 * FeaturedPurchaseController — see App\Models\Payment.
 */
class SubscriptionController extends Controller
{
    public function create(Request $request): View
    {
        return view('subscriptions.plans', [
            'plans' => Plan::query()->active()->orderBy('sort_order')->get(),
            'subscription' => $request->user()->activeSubscription(),
        ]);
    }

    public function store(Request $request, InitiateSubscriptionPayment $initiate): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->active()->findOrFail($data['plan_id']);

        try {
            $redirect = $initiate($request->user(), $plan);
        } catch (PaymentGatewayException $e) {
            report($e);

            return back()->with('error', __('app.payments.gateway_unavailable'));
        }

        return redirect()->away($redirect->url);
    }
}
