<?php

declare(strict_types=1);

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

it('lets an admin manage plans but hides them from moderators', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/plans')->assertOk();
    $this->get('/admin/plans/create')->assertOk();

    $this->actingAs(User::factory()->moderator()->create());
    $this->get('/admin/plans')->assertForbidden();
});

it('creates a plan from the admin panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePlan::class)
        ->fillForm(['name' => 'باقة جديدة', 'price' => 150, 'duration_days' => 30, 'daily_listing_limit' => 40, 'sort_order' => 1, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Plan::where('name', 'باقة جديدة')->exists())->toBeTrue();
});

it('lists plans with their subscription counts', function () {
    $this->actingAs($this->admin);
    Plan::create(['name' => 'أساسي', 'price' => 100, 'duration_days' => 30, 'daily_listing_limit' => 25]);

    Livewire::test(ListPlans::class)->assertCanSeeTableRecords(Plan::all());
});

it('lists subscriptions read-only, hidden from moderators', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/subscriptions')->assertOk();
    $this->get('/admin/subscriptions/create')->assertNotFound();

    $subscription = Subscription::factory()->for(User::factory()->create(['name' => 'مشترك تجريبي']))->active()->create();
    Livewire::test(ListSubscriptions::class)->assertCanSeeTableRecords([$subscription])->assertSee('مشترك تجريبي');

    $this->actingAs(User::factory()->moderator()->create());
    $this->get('/admin/subscriptions')->assertForbidden();
});

it('lists stores for admin oversight and lets an admin delete one, hidden from moderators', function () {
    $this->actingAs($this->admin);
    $store = Store::factory()->create(['name' => 'متجر تجريبي']);

    $this->get('/admin/stores')->assertOk();
    Livewire::test(ListStores::class)->assertCanSeeTableRecords([$store])->assertSee('متجر تجريبي');

    $this->get('/admin/stores/create')->assertNotFound();

    $this->actingAs(User::factory()->moderator()->create());
    $this->get('/admin/stores')->assertForbidden();
});
