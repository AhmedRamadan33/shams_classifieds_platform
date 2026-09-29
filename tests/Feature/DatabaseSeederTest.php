<?php

declare(strict_types=1);

use App\Models\AdBanner;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    DemoSeeder::$listingCount = 6;
});

afterEach(function () {
    DemoSeeder::$listingCount = 200;
});

it('adds the demo data when the flag is on', function () {
    config(['classifieds.seed_demo_data' => true]);

    $this->seed(DatabaseSeeder::class);

    expect(Category::count())->toBeGreaterThan(20)
        ->and(User::where('phone', '+201000000000')->exists())->toBeTrue()
        ->and(Listing::count())->toBe(6)
        ->and(AdBanner::count())->toBeGreaterThan(0);
});

it('adds the demo data in production too, as long as the flag is on (a demo deployment stays a demo)', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['classifieds.seed_demo_data' => true]);

    app(DatabaseSeeder::class)->run();

    expect(Listing::count())->toBe(6)
        ->and(User::where('phone', '+201000000000')->exists())->toBeTrue();
});

it('creates the administrator with the configured e-mail and lets them sign in with it', function () {
    config([
        'classifieds.admin.phone' => '01000000000',
        'classifieds.admin.email' => 'admin@shams.test',
        'classifieds.admin.password' => '123456789',
    ]);

    $this->seed(DatabaseSeeder::class);

    $admin = User::where('phone', '+201000000000')->sole();

    expect($admin->email)->toBe('admin@shams.test')
        ->and($admin->isAdmin())->toBeTrue();

    $this->post('/login', ['phone' => 'admin@shams.test', 'password' => '123456789'])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
});

it('leaves the demo data out when the flag is off', function () {
    config(['classifieds.seed_demo_data' => false]);

    $this->seed(DatabaseSeeder::class);

    expect(Category::count())->toBeGreaterThan(20)
        ->and(Listing::count())->toBe(0)
        ->and(AdBanner::count())->toBe(0);
});

it('never adds demo data when the flag is off, in any environment', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['classifieds.seed_demo_data' => false]);

    app(DatabaseSeeder::class)->run();

    expect(Listing::count())->toBe(0)
        ->and(User::where('phone', 'like', '+2012%')->count())->toBe(0);
});
