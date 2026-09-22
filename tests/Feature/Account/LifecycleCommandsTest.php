<?php

declare(strict_types=1);

use App\Actions\RenewListing;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingExpiringSoon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

// --------------------------------------------------------------------- expire

it('expires only active listings whose date has passed', function () {
    $due = Listing::factory()->create(['expires_at' => now()->subMinute()]);
    $dueToo = Listing::factory()->create(['expires_at' => now()->subDays(3)]);
    $future = Listing::factory()->create(['expires_at' => now()->addDay()]);
    $pending = Listing::factory()->pending()->create(['expires_at' => now()->subDay()]);
    $rejected = Listing::factory()->rejected()->create(['expires_at' => now()->subDay()]);
    $sold = Listing::factory()->sold()->create(['expires_at' => now()->subDay()]);
    $alreadyExpired = Listing::factory()->expired()->create();

    $this->artisan('listings:expire')->expectsOutputToContain('Expired 2')->assertSuccessful();

    expect($due->fresh()->status)->toBe(ListingStatus::Expired)
        ->and($dueToo->fresh()->status)->toBe(ListingStatus::Expired)
        ->and($future->fresh()->status)->toBe(ListingStatus::Active)
        ->and($pending->fresh()->status)->toBe(ListingStatus::Pending)
        ->and($rejected->fresh()->status)->toBe(ListingStatus::Rejected)
        ->and($sold->fresh()->status)->toBe(ListingStatus::Sold)
        ->and($alreadyExpired->fresh()->status)->toBe(ListingStatus::Expired);
});

it('makes an expired listing return 410 and appear under "expired" for its owner, then renewable', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->for($owner)->create(['expires_at' => now()->subHour()]);

    $this->artisan('listings:expire');

    $this->get($listing->url())->assertStatus(410);

    $this->actingAs($owner)->get('/dashboard?status=expired')->assertSee($listing->title);

    $this->post("/ads/{$listing->id}/renew")->assertSessionHas('success');

    $this->get($listing->fresh()->url())->assertOk();
});

// --------------------------------------------------------------------- remind

it('reminds owners of listings expiring within the window, once', function () {
    config(['classifieds.expiry_reminder_days' => 3]);

    $soon = Listing::factory()->for(User::factory()->create())->create(['expires_at' => now()->addDays(2)]);
    $soonToo = Listing::factory()->for(User::factory()->create())->create(['expires_at' => now()->addHours(5)]);
    $later = Listing::factory()->create(['expires_at' => now()->addDays(10)]);
    $alreadyReminded = Listing::factory()->create(['expires_at' => now()->addDay(), 'expiry_reminded_at' => now()->subHour()]);
    $pastDue = Listing::factory()->create(['expires_at' => now()->subHour()]);
    $pending = Listing::factory()->pending()->create();

    $this->artisan('listings:remind-expiring')->expectsOutputToContain('Sent 2')->assertSuccessful();

    expect($soon->fresh()->expiry_reminded_at)->not->toBeNull()
        ->and($soonToo->fresh()->expiry_reminded_at)->not->toBeNull()
        ->and($later->fresh()->expiry_reminded_at)->toBeNull()
        ->and($pastDue->fresh()->expiry_reminded_at)->toBeNull()
        ->and($pending->fresh()->expiry_reminded_at)->toBeNull()
        ->and($soon->user->notifications()->sole()->type)->toBe(ListingExpiringSoon::class)
        ->and($later->user->notifications()->count())->toBe(0)
        ->and($alreadyReminded->user->notifications()->count())->toBe(0);

    // running it again sends nothing new
    $this->artisan('listings:remind-expiring')->expectsOutputToContain('Sent 0');
    expect($soon->user->notifications()->count())->toBe(1);
});

it('resets the reminder when a listing is renewed', function () {
    $listing = Listing::factory()->create(['expires_at' => now()->addDay(), 'expiry_reminded_at' => now()]);

    app(RenewListing::class)($listing);

    expect($listing->fresh()->expiry_reminded_at)->toBeNull();
});

// ---------------------------------------------------------------------- purge

it('force deletes listings expired longer than the purge window, together with their media', function () {
    Storage::fake('public');
    config(['classifieds.purge_expired_after_days' => 90]);

    $old = Listing::factory()->create(['status' => 'expired', 'expires_at' => now()->subDays(100)]);
    $file = Fixtures::image('old.jpg');
    $old->addMedia($file->getRealPath())->preservingOriginal()->toMediaCollection(Listing::IMAGES);
    $oldMediaPath = $old->getFirstMedia(Listing::IMAGES)->getPath();

    $recent = Listing::factory()->create(['status' => 'expired', 'expires_at' => now()->subDays(30)]);
    $activeButOld = Listing::factory()->create(['status' => 'active', 'expires_at' => now()->subDays(200)]);
    $sold = Listing::factory()->sold()->create(['expires_at' => now()->subDays(200)]);
    $softDeletedOld = Listing::factory()->create(['status' => 'expired', 'expires_at' => now()->subDays(120)]);
    $softDeletedOld->delete();

    expect(file_exists($oldMediaPath))->toBeTrue();

    $this->artisan('listings:purge')->expectsOutputToContain('Purged 2')->assertSuccessful();

    expect(Listing::withTrashed()->find($old->id))->toBeNull()
        ->and(Listing::withTrashed()->find($softDeletedOld->id))->toBeNull()
        ->and(Listing::withTrashed()->find($recent->id))->not->toBeNull()
        ->and(Listing::withTrashed()->find($activeButOld->id))->not->toBeNull()
        ->and(Listing::withTrashed()->find($sold->id))->not->toBeNull()
        ->and(file_exists($oldMediaPath))->toBeFalse();
});

// ------------------------------------------------------------------ scheduler

it('schedules the lifecycle commands', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

    expect($events)->toContain('listings:expire')
        ->and($events)->toContain('listings:remind-expiring')
        ->and($events)->toContain('listings:purge');
});
