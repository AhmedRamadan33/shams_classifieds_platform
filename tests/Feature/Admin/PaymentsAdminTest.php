<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

it('lets an admin manage packages but hides them from moderators', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/packages')->assertOk();
    $this->get('/admin/packages/create')->assertOk();

    $this->actingAs(User::factory()->moderator()->create());
    $this->get('/admin/packages')->assertForbidden();
});

it('creates a package from the admin panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePackage::class)
        ->fillForm(['name' => '14 يوماً', 'days' => 14, 'price' => 120, 'sort_order' => 4, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Package::where('name', '14 يوماً')->exists())->toBeTrue();
});

it('lists packages with their payment counts', function () {
    $this->actingAs($this->admin);
    $package = Package::create(['name' => '7 أيام', 'days' => 7, 'price' => 60]);

    Livewire::test(ListPackages::class)->assertCanSeeTableRecords(Package::all());
});

it('hides payments from moderators and lets admins view but not create or edit them', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/payments')->assertOk();
    $this->get('/admin/payments/create')->assertNotFound();

    $this->actingAs(User::factory()->moderator()->create());
    $this->get('/admin/payments')->assertForbidden();
});

it('lists payments with the seller, listing, package and status', function () {
    $this->actingAs($this->admin);
    $user = User::factory()->create(['name' => 'بائع تجريبي']);
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $package = Package::create(['name' => '7 أيام', 'days' => 7, 'price' => 60]);
    $payment = Payment::create([
        'user_id' => $user->id, 'listing_id' => $listing->id, 'package_id' => $package->id,
        'gateway' => 'fake', 'amount' => 60, 'currency' => 'EGP', 'status' => PaymentStatus::Paid, 'paid_at' => now(),
    ]);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$payment])
        ->assertSee('بائع تجريبي')
        ->assertSee($listing->title);

    $this->get("/admin/payments/{$payment->id}")->assertOk()->assertSee('بائع تجريبي');
});
