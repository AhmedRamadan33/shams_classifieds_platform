<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->package = Package::create(['name' => '7 أيام', 'days' => 7, 'price' => 60, 'sort_order' => 1]);
    $this->inactivePackage = Package::create(['name' => 'قديمة', 'days' => 5, 'price' => 40, 'is_active' => false]);
    $this->owner = User::factory()->create();
    $this->listing = Listing::factory()->create(['user_id' => $this->owner->id, 'status' => 'active']);
});

it('shows the active packages on the purchase page', function () {
    $this->actingAs($this->owner)
        ->get(route('listings.feature', $this->listing))
        ->assertOk()
        ->assertSee($this->package->name)
        ->assertSee('60')
        ->assertDontSee($this->inactivePackage->name);
});

it('lets the owner buy a package, settles instantly with the fake gateway, and extends featured_until', function () {
    $this->actingAs($this->owner)
        ->post(route('listings.feature.store', $this->listing), ['package_id' => $this->package->id])
        ->assertRedirect();

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->user_id)->toBe($this->owner->id)
        ->and($payment->listing_id)->toBe($this->listing->id)
        ->and((float) $payment->amount)->toBe(60.0)
        ->and($payment->currency)->toBe(config('classifieds.currency_code'))
        ->and($payment->paid_at)->not->toBeNull();

    $this->listing->refresh();
    expect($this->listing->isFeatured())->toBeTrue()
        ->and($this->listing->featured_until->isBetween(now()->addDays(6), now()->addDays(8)))->toBeTrue();
});

it('extends the remaining time instead of restarting it when already featured', function () {
    $this->listing->update(['featured_until' => now()->addDays(3)]);

    $this->actingAs($this->owner)->post(route('listings.feature.store', $this->listing), ['package_id' => $this->package->id]);

    $this->listing->refresh();
    // 3 remaining + 7 bought = 10, not just 7 from now
    expect($this->listing->featured_until->isBetween(now()->addDays(9), now()->addDays(11)))->toBeTrue();
});

it('redirects to a result page that shows the paid confirmation', function () {
    $this->actingAs($this->owner)->post(route('listings.feature.store', $this->listing), ['package_id' => $this->package->id]);
    $payment = Payment::sole();

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee(__('app.payments.paid_title'))
        ->assertSee($this->listing->title);
});

it('rejects an unknown package', function () {
    $this->actingAs($this->owner)
        ->post(route('listings.feature.store', $this->listing), ['package_id' => 999999])
        ->assertSessionHasErrors('package_id');

    expect(Payment::count())->toBe(0);
});

it('rejects an inactive package', function () {
    $this->actingAs($this->owner)
        ->post(route('listings.feature.store', $this->listing), ['package_id' => $this->inactivePackage->id])
        ->assertNotFound();

    expect(Payment::count())->toBe(0);
});

it('forbids featuring someone else\'s listing', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('listings.feature', $this->listing))->assertForbidden();
    $this->actingAs($stranger)
        ->post(route('listings.feature.store', $this->listing), ['package_id' => $this->package->id])
        ->assertForbidden();

    expect(Payment::count())->toBe(0);
});

it('refuses to feature a listing that is not currently live', function (string $status) {
    $this->listing->update(['status' => $status]);

    $this->actingAs($this->owner)->get(route('listings.feature', $this->listing))->assertForbidden();
})->with(['pending', 'rejected', 'sold']);

it('refuses to feature an expired listing', function () {
    $this->listing->update(['status' => 'active', 'expires_at' => now()->subDay()]);

    $this->actingAs($this->owner)->get(route('listings.feature', $this->listing))->assertForbidden();
});

it('requires authentication', function () {
    $this->get(route('listings.feature', $this->listing))->assertRedirect(route('login'));
});

it('does not let one user view another user\'s payment result page', function () {
    $this->actingAs($this->owner)->post(route('listings.feature.store', $this->listing), ['package_id' => $this->package->id]);
    $payment = Payment::sole();

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('payments.show', $payment))->assertForbidden();
});
