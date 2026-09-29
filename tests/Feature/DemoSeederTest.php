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
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    DemoSeeder::$listingCount = 40;
    config(['classifieds.seed_demo_data' => true]);
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

    Listing::with(['category', 'media'])->get()->each(function (Listing $listing) {
        $photos = collect(glob(database_path('seeders/data/images/'.$listing->category->slug.'/*.jpg')))->map(fn (string $file) => md5_file($file))->all();

        foreach ($listing->media as $media) {
            expect($photos)->toContain(md5_file($media->getPath()));
        }
    });

    $constants = (new ReflectionClass(DemoSeeder::class))->getConstants();

    Listing::with(['governorate', 'city', 'fieldValues.field'])->get()->each(function (Listing $listing) use ($constants) {
        if (isset($constants['LOCATIONS'][$listing->title])) {
            [$governorate, $city] = $constants['LOCATIONS'][$listing->title];

            expect($listing->governorate->name)->toBe($governorate)
                ->and($listing->city?->name)->toBe($city);
        }

        foreach ($constants['ATTRIBUTES'][$listing->title] ?? [] as $key => $expected) {
            $stored = $listing->fieldValues->first(fn ($row) => $row->field?->key === $key);

            if ($expected === null) {
                expect($stored)->toBeNull();

                continue;
            }

            expect($stored?->value)->toBe((string) $expected);
        }
    });

    expect(Listing::where('status', ListingStatus::Active->value)->count())->toBeGreaterThan(20);

    $moderator = User::where('phone', '+201111111111')->sole();
    expect($moderator->hasRole('moderator'))->toBeTrue()->and($moderator->hasVerifiedPhone())->toBeTrue();

    expect($moderator->email)->toBe('moderator@shams.test');

    User::where('phone', 'like', '+2012%')->orderBy('id')->get()->each(function (User $user, int $index) {
        expect($user->email)->toBe('user'.($index + 1).'@shams.test');
    });

    User::all()->each(fn (User $user) => expect(Hash::check('123456789', $user->password))->toBeTrue());
    expect(AdBanner::count())->toBe(7)
        ->and(AdBanner::doesntHave('media')->count())->toBe(0)
        ->and(AdBanner::whereNotNull('listing_id')->count())->toBe(1)
        ->and(AdBanner::where('status', 'active')->count())->toBe(5)
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
        ->and(AdBanner::count())->toBe(7)
        ->and(Store::count())->toBe(4)
        ->and(SavedSearch::count())->toBe(2);
});

it('ships a set of real photographs for every leaf category and every sponsored banner', function () {
    $this->seed(CategorySeeder::class);

    Category::whereDoesntHave('children')->pluck('slug')->each(function (string $slug) {
        $files = glob(database_path("seeders/data/images/{$slug}/*.jpg"));

        expect(count($files))->toBeGreaterThanOrEqual(4, "category {$slug} needs at least four photos");

        foreach ($files as $file) {
            [$width, $height] = getimagesize($file);

            expect($width)->toBeGreaterThanOrEqual(600)
                ->and($height)->toBeGreaterThanOrEqual(300)
                ->and(filesize($file))->toBeGreaterThan(20_000);
        }
    });

    foreach (['tourism', 'dental', 'movers', 'restaurant', 'electronics', 'coins-sign'] as $name) {
        [$width, $height] = getimagesize(database_path("seeders/data/images/banners/{$name}.jpg"));

        expect($width / $height)->toBeGreaterThan(3.0)->toBeLessThan(3.4);
    }
});

it('only references photos that exist and titles that are in the catalog', function () {
    $constants = (new ReflectionClass(DemoSeeder::class))->getConstants();
    $titles = [];

    foreach ($constants['CATALOG'] as $slug => [, $entries]) {
        foreach ($entries as $title => $numbers) {
            $titles[$title] = true;

            foreach ($numbers as $number) {
                expect(database_path("seeders/data/images/{$slug}/{$number}.jpg"))->toBeFile();
            }
        }
    }

    expect(array_diff_key($constants['LOCATIONS'], $titles))->toBe([])
        ->and(array_diff_key($constants['ATTRIBUTES'], $titles))->toBe([]);
});

it('refuses to run when SEED_DEMO_DATA is off', function () {
    config(['classifieds.seed_demo_data' => false]);

    app(DemoSeeder::class)->run();

    expect(Listing::count())->toBe(0)
        ->and(User::count())->toBe(0)
        ->and(AdBanner::count())->toBe(0)
        ->and(Store::count())->toBe(0)
        ->and(SavedSearch::count())->toBe(0);
});

it('runs in production too, as long as SEED_DEMO_DATA is on (this is meant to seed a live demo deployment)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    app(DemoSeeder::class)->run();

    expect(Listing::count())->toBe(40)
        ->and(User::where('phone', 'like', '+2012%')->count())->toBe(12);
});
