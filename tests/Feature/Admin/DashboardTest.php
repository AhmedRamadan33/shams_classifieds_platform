<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Filament\Resources\AdBanners\Pages\ListAdBanners;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\EngagementOverview;
use App\Filament\Widgets\ListingsOverview;
use App\Filament\Widgets\RevenueOverview;
use App\Filament\Widgets\UsersOverview;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Livewire\Livewire;

function dashboardStats(string $widget): array
{
    $instance = app($widget);
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    return collect($stats)->mapWithKeys(fn ($stat) => [(string) $stat->getLabel() => (string) $stat->getValue()])->all();
}

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('counts listings by status, features, views and freshness', function () {
    Listing::factory()->featured()->create(['views' => 10]);
    Listing::factory()->count(2)->create(['views' => 20]);
    Listing::factory()->pending()->create(['views' => 0]);
    Listing::factory()->count(2)->rejected()->create(['views' => 5]);
    Listing::factory()->expired()->create(['views' => 3]);
    Listing::factory()->sold()->create(['views' => 7]);
    Listing::factory()->create(['views' => 0, 'created_at' => now()->subDays(20)]);

    $stats = dashboardStats(ListingsOverview::class);

    expect($stats[__('app.admin.stats.total_listings')])->toBe('9')
        ->and($stats[__('app.admin.stats.active_listings')])->toBe('4')
        ->and($stats[__('app.admin.stats.featured_listings')])->toBe('1')
        ->and($stats[__('app.admin.stats.rejected_listings')])->toBe('2')
        ->and($stats[__('app.admin.stats.expired_listings')])->toBe('1')
        ->and($stats[__('app.admin.stats.sold_listings')])->toBe('1')
        ->and($stats[__('app.admin.stats.listings_today')])->toBe('8')
        ->and($stats[__('app.admin.stats.listings_week')])->toBe('8')
        ->and($stats[__('app.admin.stats.total_views')])->toBe('70')
        ->and($stats[__('app.admin.stats.average_views')])->toBe('7.8');
});

it('does not count deleted listings', function () {
    Listing::factory()->count(2)->create();
    Listing::factory()->create()->delete();

    expect(dashboardStats(ListingsOverview::class)[__('app.admin.stats.total_listings')])->toBe('2');
});

it('counts users, verified numbers, bans and staff', function () {
    User::factory()->moderator()->create();
    User::factory()->create();
    User::factory()->unverified()->create();
    User::factory()->banned()->create();

    $stats = dashboardStats(UsersOverview::class);

    expect($stats[__('app.admin.stats.total_users')])->toBe('5')
        ->and($stats[__('app.admin.stats.verified_users')])->toBe('4')
        ->and($stats[__('app.admin.stats.banned_users')])->toBe('1')
        ->and($stats[__('app.admin.stats.staff')])->toBe('2')
        ->and($stats[__('app.admin.stats.new_users_week')])->toBe('5');
});

it('adds up revenue from paid payments only', function () {
    $listing = Listing::factory()->create();
    $package = Package::create(['name' => '7 أيام', 'days' => 7, 'price' => 100]);
    $payment = fn (float $amount, PaymentStatus $status, $paidAt) => Payment::create([
        'user_id' => $listing->user_id, 'listing_id' => $listing->id, 'package_id' => $package->id,
        'gateway' => 'fake', 'amount' => $amount, 'currency' => 'EGP', 'status' => $status, 'paid_at' => $paidAt,
    ]);

    $payment(100, PaymentStatus::Paid, now());
    $payment(250.5, PaymentStatus::Paid, now()->subMonths(2));
    $payment(999, PaymentStatus::Pending, null);
    $payment(500, PaymentStatus::Failed, null);

    $stats = dashboardStats(RevenueOverview::class);

    expect($stats[__('app.admin.stats.revenue_total')])->toBe('350.50 '.config('classifieds.currency_label'))
        ->and($stats[__('app.admin.stats.revenue_today')])->toBe('100 '.config('classifieds.currency_label'))
        ->and($stats[__('app.admin.stats.featured_revenue')])->toBe('350.50 '.config('classifieds.currency_label'))
        ->and($stats[__('app.admin.stats.subscription_revenue')])->toBe('0 '.config('classifieds.currency_label'))
        ->and($stats[__('app.admin.stats.paid_payments')])->toBe('2')
        ->and($stats[__('app.admin.stats.pending_payments')])->toBe('1');
});

it('reports engagement figures with zero data without failing', function () {
    $stats = dashboardStats(EngagementOverview::class);

    expect($stats)->toHaveCount(6)
        ->and($stats[__('app.admin.stats.favorites')])->toBe('0');
});

it('shows only statistics on the dashboard: no welcome card and no sign-out button in the page', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSee(__('app.admin.stats.moderation'))
        ->assertSee(__('app.admin.stats.listings'))
        ->assertSee(__('app.admin.stats.engagement'))
        ->assertSee(__('app.admin.stats.users'))
        ->assertSee(__('app.admin.stats.revenue'))
        ->assertSee(__('app.admin.charts.listings_per_day'))
        ->assertSee(__('app.admin.charts.top_categories'))
        ->assertDontSee('fi-account-widget');
});

it('shows every statistic to an admin and hides money and user figures from moderators', function () {
    $this->actingAs(User::factory()->moderator()->create());

    $this->get('/admin')
        ->assertOk()
        ->assertSee(__('app.admin.stats.listings'))
        ->assertSee(__('app.admin.stats.engagement'))
        ->assertDontSee(__('app.admin.stats.revenue'))
        ->assertDontSee(__('app.admin.stats.new_users_week'))
        ->assertDontSee(__('app.admin.charts.revenue_per_month'));

    expect(UsersOverview::canView())->toBeFalse()
        ->and(RevenueOverview::canView())->toBeFalse();
});

it('has an icon in the top bar that opens the website in a new tab', function () {
    $html = $this->get('/admin')->assertOk()->getContent();

    expect($html)->toContain('href="'.url('/').'"')
        ->and($html)->toContain(__('app.admin.open_website'))
        ->and($html)->toMatch('/<a(?=[^>]*href="'.preg_quote(url('/'), '/').'")(?=[^>]*target="_blank")[^>]*>/');
});

it('shows a user icon instead of initials in the top bar', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSee('data:image/svg+xml;base64,', escape: false)
        ->assertDontSee('ui-avatars.com');
});

it('puts the row actions of every table in one dropdown', function (string $page) {
    $group = Livewire::test($page)->instance()->getTable()->getRecordActions();

    expect($group)->toHaveCount(1)
        ->and($group[0])->toBeInstanceOf(ActionGroup::class);
})->with([
    'listings' => ListListings::class,
    'banners' => ListAdBanners::class,
    'reports' => ListReports::class,
    'reviews' => ListReviews::class,
    'stores' => ListStores::class,
    'users' => ListUsers::class,
]);
