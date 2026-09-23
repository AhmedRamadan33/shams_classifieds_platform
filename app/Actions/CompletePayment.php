<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final class CompletePayment
{
    public function __invoke(Payment $payment, ?string $gatewayTransactionId = null, ?array $meta = null): Payment
    {
        return DB::transaction(function () use ($payment, $gatewayTransactionId, $meta): Payment {
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->isPaid()) {
                return $payment;
            }

            if ($payment->isForSubscription()) {
                $this->activateSubscription($payment);
            } else {
                $this->extendFeatured($payment);
            }

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'gateway_transaction_id' => $gatewayTransactionId ?? $payment->gateway_transaction_id,
                'meta' => $meta ?? $payment->meta,
            ])->save();

            return $payment;
        });
    }

    private function extendFeatured(Payment $payment): void
    {
        $listing = $payment->listing()->lockForUpdate()->firstOrFail();
        $package = $payment->package;

        $from = $listing->featured_until?->isFuture() ? $listing->featured_until : now();
        $listing->forceFill(['featured_until' => $from->copy()->addDays($package->days)])->save();
    }

    private function activateSubscription(Payment $payment): void
    {
        $subscription = $payment->subscription()->lockForUpdate()->firstOrFail();
        $plan = $subscription->plan;

        $from = $subscription->isActive() ? $subscription->expires_at : now();

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'starts_at' => $subscription->starts_at ?? now(),
            'expires_at' => $from->copy()->addDays($plan->duration_days),
        ])->save();
    }
}
