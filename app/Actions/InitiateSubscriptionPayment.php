<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRedirect;

/**
 * Creates (or reuses) a Subscription and a Payment for it, and asks the configured PaymentGateway to
 * charge it. Mirrors InitiateFeaturedPayment; see it for the failure-handling rationale.
 *
 * A user who is already actively subscribed and buys again (the same plan, to renew, or a different
 * one, to switch) keeps their existing Subscription row: CompletePayment extends its expires_at from
 * whichever is later, now or the current expiry, by the newly bought plan's duration — so the switch
 * takes the new plan's daily limit immediately without losing already-paid-for remaining time.
 */
final class InitiateSubscriptionPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @throws PaymentGatewayException
     */
    public function __invoke(User $user, Plan $plan): PaymentRedirect
    {
        $existing = $user->activeSubscription();
        $previousPlanId = $existing?->plan_id;

        if ($existing !== null) {
            $subscription = $existing;
            $subscription->update(['plan_id' => $plan->id]);
        } else {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Pending,
            ]);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'gateway' => (string) config('services.payments.driver'),
            'amount' => $plan->price,
            'currency' => (string) config('classifieds.currency_code'),
        ]);

        try {
            return $this->gateway->charge($payment);
        } catch (PaymentGatewayException $e) {
            $payment->delete();

            // Nothing was charged: a freshly created subscription is removed entirely; an existing,
            // already-active one is restored to what it was, not left pointing at the unpaid plan.
            if ($existing === null) {
                $subscription->delete();
            } else {
                $subscription->update(['plan_id' => $previousPlanId]);
            }

            throw $e;
        }
    }
}
