<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\AdBanner;
use App\Models\AdPackage;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->homePackage = AdPackage::factory()->create(['name' => '7 أيام - الرئيسية', 'placement' => 'home_top', 'duration_days' => 7, 'price' => 100]);
    $this->sidebarPackage = AdPackage::factory()->create(['name' => 'باقة الشريط الجانبي', 'placement' => 'search_sidebar', 'duration_days' => 7, 'price' => 40]);
    $this->banner = AdBanner::factory()->for($this->owner)->approved()->create(['placement' => 'home_top']);
});

it('only offers packages matching the banner\'s placement', function () {
    $this->actingAs($this->owner)->get(route('ad-banners.purchase', $this->banner))
        ->assertOk()
        ->assertSee($this->homePackage->name)
        ->assertDontSee($this->sidebarPackage->name);
});

it('lets the advertiser pay, settles instantly with the fake gateway, and activates the banner', function () {
    $this->actingAs($this->owner)
        ->post(route('ad-banners.purchase.store', $this->banner), ['ad_package_id' => $this->homePackage->id])
        ->assertRedirect();

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->ad_banner_id)->toBe($this->banner->id)
        ->and((float) $payment->amount)->toBe(100.0);

    $this->banner->refresh();
    expect($this->banner->status->value)->toBe('active')
        ->and($this->banner->ad_package_id)->toBe($this->homePackage->id)
        ->and($this->banner->expires_at->isBetween(now()->addDays(6), now()->addDays(8)))->toBeTrue();
});

it('extends the remaining time instead of restarting it when renewing an active banner', function () {
    $this->banner->update(['status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(3)]);

    $this->actingAs($this->owner)->post(route('ad-banners.purchase.store', $this->banner), ['ad_package_id' => $this->homePackage->id]);

    $this->banner->refresh();
    expect($this->banner->expires_at->isBetween(now()->addDays(9), now()->addDays(11)))->toBeTrue();
});

it('lets an expired banner be renewed without re-moderation', function () {
    $this->banner->update(['status' => 'expired', 'starts_at' => now()->subDays(10), 'expires_at' => now()->subDay()]);

    $this->actingAs($this->owner)
        ->post(route('ad-banners.purchase.store', $this->banner), ['ad_package_id' => $this->homePackage->id])
        ->assertRedirect();

    expect($this->banner->fresh()->status->value)->toBe('active');
});

it('refuses to sell a package from a different placement', function () {
    $this->actingAs($this->owner)
        ->post(route('ad-banners.purchase.store', $this->banner), ['ad_package_id' => $this->sidebarPackage->id])
        ->assertNotFound();

    expect(Payment::count())->toBe(0);
});

it('forbids purchasing a still-pending banner', function () {
    $pending = AdBanner::factory()->for($this->owner)->create(['placement' => 'home_top']);

    $this->actingAs($this->owner)->get(route('ad-banners.purchase', $pending))->assertForbidden();
    $this->actingAs($this->owner)
        ->post(route('ad-banners.purchase.store', $pending), ['ad_package_id' => $this->homePackage->id])
        ->assertForbidden();
});

it('forbids purchasing someone else\'s banner', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('ad-banners.purchase', $this->banner))->assertForbidden();
});

it('requires authentication', function () {
    $this->get(route('ad-banners.purchase', $this->banner))->assertRedirect(route('login'));
});

it('redirects to a result page that shows the paid confirmation', function () {
    $this->actingAs($this->owner)->post(route('ad-banners.purchase.store', $this->banner), ['ad_package_id' => $this->homePackage->id]);
    $payment = Payment::sole();

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee(__('app.payments.paid_title'));
});

it('tracks a click and redirects to the target url', function () {
    $active = AdBanner::factory()->for($this->owner)->active()->create(['target_url' => 'https://example.com/deal']);

    $this->get(route('ad-banners.click', $active))->assertRedirect('https://example.com/deal');

    expect($active->fresh()->clicks)->toBe(1);
});
