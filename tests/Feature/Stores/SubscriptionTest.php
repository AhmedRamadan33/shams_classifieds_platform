<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Governorate;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ListingLimits;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRedirect;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->plan = Plan::create(['name' => 'أساسي', 'price' => 100, 'duration_days' => 30, 'daily_listing_limit' => 25]);
    $this->inactivePlan = Plan::create(['name' => 'قديمة', 'price' => 80, 'duration_days' => 30, 'daily_listing_limit' => 25, 'is_active' => false]);
    $this->user = User::factory()->create();
});

it('shows the active plans on the subscribe page', function () {
    $this->actingAs($this->user)
        ->get('/subscribe')
        ->assertOk()
        ->assertSee($this->plan->name)
        ->assertDontSee($this->inactivePlan->name);
});

it('subscribes the user, settling instantly with the fake gateway', function () {
    $this->actingAs($this->user)
        ->post('/subscribe', ['plan_id' => $this->plan->id])
        ->assertRedirect();

    $subscription = Subscription::sole();
    expect($subscription->user_id)->toBe($this->user->id)
        ->and($subscription->plan_id)->toBe($this->plan->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->starts_at)->not->toBeNull()
        ->and($subscription->expires_at->isBetween(now()->addDays(29), now()->addDays(31)))->toBeTrue();

    $payment = Payment::sole();
    expect($payment->isForSubscription())->toBeTrue()
        ->and((float) $payment->amount)->toBe(100.0);
});

it('extends the remaining time instead of restarting it when already subscribed to the same plan', function () {
    $subscription = Subscription::factory()->for($this->user)->for($this->plan, 'plan')->active()->create(['expires_at' => now()->addDays(5)]);

    $this->actingAs($this->user)->post('/subscribe', ['plan_id' => $this->plan->id]);

    expect(Subscription::count())->toBe(1); // the existing row is reused, not duplicated
    expect($subscription->fresh()->expires_at->isBetween(now()->addDays(34), now()->addDays(36)))->toBeTrue();
});

it('switches to a new plan and extends from the current expiry when upgrading', function () {
    $subscription = Subscription::factory()->for($this->user)->for($this->plan, 'plan')->active()->create(['expires_at' => now()->addDays(5)]);
    $pro = Plan::create(['name' => 'احترافي', 'price' => 250, 'duration_days' => 60, 'daily_listing_limit' => 80]);

    $this->actingAs($this->user)->post('/subscribe', ['plan_id' => $pro->id]);

    $subscription->refresh();
    expect(Subscription::count())->toBe(1)
        ->and($subscription->plan_id)->toBe($pro->id)
        ->and($subscription->expires_at->isBetween(now()->addDays(64), now()->addDays(66)))->toBeTrue();
});

it('restores the original plan if the gateway fails while switching plans', function () {
    $subscription = Subscription::factory()->for($this->user)->for($this->plan, 'plan')->active()->create(['expires_at' => now()->addDays(5)]);
    $pro = Plan::create(['name' => 'احترافي', 'price' => 250, 'duration_days' => 60, 'daily_listing_limit' => 80]);
    app()->instance(
        PaymentGateway::class,
        new class implements PaymentGateway
        {
            public function charge(Payment $payment): PaymentRedirect
            {
                throw PaymentGatewayException::provider('test', 'down');
            }
        },
    );

    $this->actingAs($this->user)
        ->post('/subscribe', ['plan_id' => $pro->id])
        ->assertSessionHas('error', __('app.payments.gateway_unavailable'));

    expect($subscription->fresh()->plan_id)->toBe($this->plan->id)
        ->and(Payment::count())->toBe(0);
});

it('shows the subscription result on the shared payment result page', function () {
    $this->actingAs($this->user)->post('/subscribe', ['plan_id' => $this->plan->id]);
    $payment = Payment::sole();

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee(__('app.payments.paid_title'))
        ->assertSee($this->plan->name);
});

it('rejects an inactive or unknown plan', function () {
    $this->actingAs($this->user)->post('/subscribe', ['plan_id' => $this->inactivePlan->id])->assertNotFound();
    expect(Subscription::count())->toBe(0);

    $this->actingAs($this->user)->post('/subscribe', ['plan_id' => 999999])->assertSessionHasErrors('plan_id');
});

it('requires authentication to subscribe', function () {
    $this->get('/subscribe')->assertRedirect('/login');
    $this->post('/subscribe', ['plan_id' => $this->plan->id])->assertRedirect('/login');
});

// -------------------------------------------------------------- ListingLimits

it('uses the site default when the user has no active subscription', function () {
    config(['classifieds.daily_listing_limit' => 10]);

    expect(ListingLimits::dailyLimitFor($this->user))->toBe(10);
});

it('uses the subscription plan\'s limit when it is higher than the default', function () {
    config(['classifieds.daily_listing_limit' => 10]);
    Subscription::factory()->for($this->user)->for($this->plan, 'plan')->active()->create();

    expect(ListingLimits::dailyLimitFor($this->user->fresh()))->toBe(25);
});

it('falls back to the site default when the subscription plan\'s limit is lower', function () {
    config(['classifieds.daily_listing_limit' => 30]);
    Subscription::factory()->for($this->user)->for($this->plan, 'plan')->active()->create();

    expect(ListingLimits::dailyLimitFor($this->user->fresh()))->toBe(30);
});

it('ignores an expired subscription', function () {
    config(['classifieds.daily_listing_limit' => 10]);
    Subscription::factory()->for($this->user)->for($this->plan, 'plan')->expired()->create();

    expect(ListingLimits::dailyLimitFor($this->user->fresh()))->toBe(10);
});

it('actually raises the daily listing limit enforced when posting ads', function () {
    config(['classifieds.daily_listing_limit' => 2]);
    Subscription::factory()->for($this->user)->for($this->plan, 'plan')->active()->create(); // limit 25
    $tree = Fixtures::carsTree();
    $governorate = Governorate::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->actingAs($this->user)
            ->post('/ads', Fixtures::listingPayload($tree['leaf'], $governorate, null, ['title' => "إعلان رقم {$i} للاختبار"]))
            ->assertSessionHasNoErrors();
    }
});

// ------------------------------------------------------------ Paymob webhook (subscriptions)

it('activates a subscription through the Paymob webhook, same as the fake gateway', function () {
    config(['services.payments.driver' => 'paymob', 'services.paymob.hmac_secret' => 'sub-secret']);
    $this->actingAs($this->user);
    app()->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function charge(Payment $payment): PaymentRedirect
        {
            $payment->update(['gateway_order_id' => '4242']);

            return new PaymentRedirect('https://accept.paymob.com/iframe');
        }
    });

    $this->post('/subscribe', ['plan_id' => $this->plan->id]);
    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::Pending);

    $fields = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
        'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
        'source_data.type', 'success'];
    $transaction = [
        'amount_cents' => 10000, 'created_at' => '2026-01-01T00:00:00', 'currency' => 'EGP',
        'error_occured' => false, 'has_parent_transaction' => false, 'id' => 999,
        'integration_id' => 1, 'is_3d_secure' => true, 'is_auth' => false, 'is_capture' => false,
        'is_refunded' => false, 'is_standalone_payment' => true, 'is_voided' => false,
        'order' => ['id' => 4242], 'owner' => 1, 'pending' => false,
        'source_data' => ['pan' => '1234', 'sub_type' => 'MasterCard', 'type' => 'card'],
        'success' => true,
    ];
    $concatenated = collect($fields)->map(function (string $path) use ($transaction) {
        $value = data_get($transaction, $path);

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    })->implode('');
    $hmac = hash_hmac('sha512', $concatenated, 'sub-secret');

    $this->postJson(route('payments.webhook.paymob', ['hmac' => $hmac]), ['obj' => $transaction])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    $subscription = Subscription::sole();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->expires_at->isBetween(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});
