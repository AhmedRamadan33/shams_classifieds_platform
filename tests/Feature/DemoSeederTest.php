<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    DemoSeeder::$listingCount = 40;
});

afterEach(function () {
    DemoSeeder::$listingCount = 200;
});

it('creates demo listings across categories with dynamic values, images, users, a moderator, favorites and reports', function () {
    $this->seed(DemoSeeder::class);

    expect(Listing::count())->toBe(40)
        ->and(User::count())->toBeGreaterThanOrEqual(13)
        ->and(Favorite::count())->toBeGreaterThan(0)
        ->and(Report::count())->toBeGreaterThan(0);

    $leafIds = Category::whereDoesntHave('children')->pluck('id');
    expect(Listing::whereNotIn('category_id', $leafIds)->count())->toBe(0)
        ->and(Listing::has('fieldValues')->count())->toBeGreaterThan(20);

    expect(Listing::doesntHave('media')->count())->toBe(0)
        ->and(Listing::where('search_text', '')->count())->toBe(0);

    expect(Listing::where('status', ListingStatus::Active->value)->count())->toBeGreaterThan(20);

    $moderator = User::where('phone', '+201111111111')->sole();
    expect($moderator->hasRole('moderator'))->toBeTrue()->and($moderator->hasVerifiedPhone())->toBeTrue();
});

it('is safe to run twice', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Listing::count())->toBe(40);
});

it('refuses to run outside the local environment', function () {
    $this->app->detectEnvironment(fn () => 'production');

    app(DemoSeeder::class)->run();

    expect(Listing::count())->toBe(0)->and(User::count())->toBe(0);
});
