<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRedirect;

final class InitiateFeaturedPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function __invoke(User $user, Listing $listing, Package $package): PaymentRedirect
    {
        $payment = Payment::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'package_id' => $package->id,
            'gateway' => (string) config('services.payments.driver'),
            'amount' => $package->price,
            'currency' => (string) config('classifieds.currency_code'),
            'status' => PaymentStatus::Pending,
        ]);

        try {
            return $this->gateway->charge($payment);
        } catch (PaymentGatewayException $e) {
            $payment->delete();

            throw $e;
        }
    }
}
