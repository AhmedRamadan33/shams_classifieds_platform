<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\AdBanner;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Models\Review;
use App\Models\SavedSearch;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShowcaseAccountsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    DemoSeeder::$listingCount = 150;
    config([
        'classifieds.admin.phone' => '01000000000',
        'classifieds.admin.email' => 'admin@shams.test',
        'classifieds.admin.password' => '123456789',
        'classifieds.seed_demo_data' => true,
    ]);
});

afterEach(function () {
    DemoSeeder::$listingCount = 200;
});

it('does nothing when the showcase user does not exist', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(ShowcaseAccountsSeeder::class);

    expect(Store::count())->toBe(0);
});

it('gives the admin and the showcase user real, connected data across the whole system', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->seed(ShowcaseAccountsSeeder::class);

    $admin = User::where('email', 'admin@shams.test')->sole();
    $user = User::where('email', 'user1@shams.test')->sole();

    foreach ([ListingStatus::Pending, ListingStatus::Rejected, ListingStatus::Expired, ListingStatus::Sold] as $status) {
        expect(Listing::where('user_id', $user->id)->where('status', $status->value)->exists())->toBeTrue();
    }

    expect(Listing::where('user_id', $user->id)->where('status', ListingStatus::Active->value)->whereNotNull('featured_until')->count())->toBeGreaterThanOrEqual(1)
        ->and(Listing::where('user_id', $user->id)->where('status', ListingStatus::Active->value)->whereBetween('expires_at', [now(), now()->addDays(3)])->exists())->toBeTrue();

    expect(Listing::where('user_id', $admin->id)->count())->toBeGreaterThanOrEqual(5);

    expect(Favorite::where('user_id', $user->id)->count())->toBeGreaterThanOrEqual(10)
        ->and(Favorite::where('user_id', $admin->id)->count())->toBeGreaterThanOrEqual(4);

    $userStore = Store::where('user_id', $user->id)->sole();
    $adminStore = Store::where('user_id', $admin->id)->sole();

    expect($userStore->slug)->not->toBe($adminStore->slug)
        ->and(Subscription::where('user_id', $user->id)->where('status', 'active')->exists())->toBeTrue()
        ->and(Subscription::where('user_id', $admin->id)->where('status', 'active')->exists())->toBeTrue();

    expect(Conversation::where('buyer_id', $user->id)->orWhere('seller_id', $user->id)->count())->toBeGreaterThanOrEqual(4)
        ->and(Conversation::where('buyer_id', $admin->id)->orWhere('seller_id', $admin->id)->count())->toBeGreaterThanOrEqual(2)
        ->and(Message::whereIn('conversation_id', Conversation::where('buyer_id', $user->id)->orWhere('seller_id', $user->id)->pluck('id'))->count())->toBeGreaterThan(5);

    expect(Review::where('seller_id', $user->id)->count())->toBeGreaterThanOrEqual(3)
        ->and(Review::where('reviewer_id', $user->id)->count())->toBeGreaterThanOrEqual(3)
        ->and(Review::where('seller_id', $admin->id)->count())->toBeGreaterThanOrEqual(1);

    expect(SavedSearch::where('user_id', $user->id)->count())->toBeGreaterThanOrEqual(2)
        ->and(SavedSearch::where('user_id', $admin->id)->exists())->toBeTrue();

    expect(Report::where('user_id', $user->id)->count())->toBeGreaterThanOrEqual(1);

    expect($user->notifications()->count())->toBeGreaterThanOrEqual(3)
        ->and($user->unreadNotifications()->count())->toBeGreaterThanOrEqual(1);

    expect(AdBanner::where('user_id', $user->id)->count())->toBeGreaterThan(0);

    expect($user->fresh()->notify_email)->toBeTrue()
        ->and($admin->fresh()->notify_email)->toBeTrue();
});

it('is safe to run twice and does not duplicate data', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->seed(ShowcaseAccountsSeeder::class);

    $counts = [
        'stores' => Store::count(),
        'favorites' => Favorite::count(),
        'conversations' => Conversation::count(),
        'messages' => Message::count(),
        'reviews' => Review::count(),
        'saved_searches' => SavedSearch::count(),
        'reports' => Report::count(),
        'notifications' => User::where('email', 'user1@shams.test')->sole()->notifications()->count(),
    ];

    $this->seed(ShowcaseAccountsSeeder::class);

    expect(Store::count())->toBe($counts['stores'])
        ->and(Favorite::count())->toBe($counts['favorites'])
        ->and(Conversation::count())->toBe($counts['conversations'])
        ->and(Message::count())->toBe($counts['messages'])
        ->and(Review::count())->toBe($counts['reviews'])
        ->and(SavedSearch::count())->toBe($counts['saved_searches'])
        ->and(Report::count())->toBe($counts['reports'])
        ->and(User::where('email', 'user1@shams.test')->sole()->notifications()->count())->toBe($counts['notifications']);
});

it('still gives the showcase user data when there is no administrator yet', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(ShowcaseAccountsSeeder::class);

    $user = User::where('email', 'user1@shams.test')->sole();

    expect(Store::where('user_id', $user->id)->exists())->toBeTrue()
        ->and(SavedSearch::where('user_id', $user->id)->count())->toBeGreaterThanOrEqual(2);
});
