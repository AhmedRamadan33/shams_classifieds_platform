<?php

declare(strict_types=1);

use App\Enums\AdBannerStatus;
use App\Models\AdBanner;
use Illuminate\Console\Scheduling\Schedule;

it('expires only active ad banners whose date has passed', function () {
    $due = AdBanner::factory()->active()->create(['expires_at' => now()->subMinute()]);
    $future = AdBanner::factory()->active()->create(['expires_at' => now()->addDay()]);
    $pending = AdBanner::factory()->create();
    $alreadyExpired = AdBanner::factory()->expired()->create();

    $this->artisan('ad-banners:expire')->expectsOutputToContain('Expired 1')->assertSuccessful();

    expect($due->fresh()->status)->toBe(AdBannerStatus::Expired)
        ->and($future->fresh()->status)->toBe(AdBannerStatus::Active)
        ->and($pending->fresh()->status)->toBe(AdBannerStatus::Pending)
        ->and($alreadyExpired->fresh()->status)->toBe(AdBannerStatus::Expired);
});

it('schedules the ad-banners:expire command', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

    expect($events)->toContain('ad-banners:expire');
});
