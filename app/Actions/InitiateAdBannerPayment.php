<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\AdBanner;
use App\Models\AdPackage;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRedirect;

final class InitiateAdBannerPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function __invoke(User $user, AdBanner $adBanner, AdPackage $package): PaymentRedirect
    {
        $adBanner->forceFill(['ad_package_id' => $package->id])->save();

        $payment = Payment::create([
            'user_id' => $user->id,
            'ad_banner_id' => $adBanner->id,
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
