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

final class InitiateSubscriptionPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

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

            if ($existing === null) {
                $subscription->delete();
            } else {
                $subscription->update(['plan_id' => $previousPlanId]);
            }

            throw $e;
        }
    }
}
