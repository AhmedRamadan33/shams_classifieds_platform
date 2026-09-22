<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * A payment provider that can charge a Payment and later confirm it was paid. Controllers only
 * depend on this interface, so a provider can be swapped from AppServiceProvider without touching
 * FeaturedPurchaseController. See FakePaymentGateway (local/testing) and PaymobGateway (production).
 */
interface PaymentGateway
{
    /**
     * Start the payment. Sets $payment's gateway_order_id (and any other gateway bookkeeping) and
     * returns where the browser should go next to complete it.
     */
    public function charge(Payment $payment): PaymentRedirect;
}
