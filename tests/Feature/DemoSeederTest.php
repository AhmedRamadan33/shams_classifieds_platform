<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\AdBanner;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Review;
use App\Models\SavedSearch;
use App\Models\Store;
use App\Models\Subscription;
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

    expect(AdBanner::count())->toBe(6)
        ->and(AdBanner::doesntHave('media')->count())->toBe(0)
        ->and(AdBanner::whereNotNull('listing_id')->count())->toBe(1)
        ->and(AdBanner::where('status', 'active')->count())->toBe(4)
        ->and(AdBanner::where('status', 'pending')->count())->toBe(1)
        ->and(AdBanner::where('status', 'rejected')->count())->toBe(1);

    expect(Listing::where('status', ListingStatus::Active->value)->whereNotNull('featured_until')->count())->toBeGreaterThanOrEqual(6)
        ->and(Payment::where('status', 'paid')->count())->toBeGreaterThan(0);

    expect(Store::count())->toBe(4)
        ->and(Subscription::where('status', 'active')->count())->toBe(2);

    expect(Review::count())->toBeGreaterThan(0);

    expect(Conversation::count())->toBeGreaterThan(0)
        ->and(Message::count())->toBeGreaterThan(0);

    expect(SavedSearch::count())->toBe(2);
});

it('is safe to run twice', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Listing::count())->toBe(40)
        ->and(AdBanner::count())->toBe(6)
        ->and(Store::count())->toBe(4)
        ->and(SavedSearch::count())->toBe(2);
});

it('refuses to run outside the local environment', function () {
    $this->app->detectEnvironment(fn () => 'production');

    app(DemoSeeder::class)->run();

    expect(Listing::count())->toBe(0)
        ->and(User::count())->toBe(0)
        ->and(AdBanner::count())->toBe(0)
        ->and(Store::count())->toBe(0)
        ->and(SavedSearch::count())->toBe(0);
});
