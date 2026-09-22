<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ListingApproved;
use App\Notifications\ListingRejected;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Fixtures;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    config(['classifieds.listing_duration_days' => 30]);

    $this->moderator = User::factory()->moderator()->create();
    $this->owner = User::factory()->create(['name' => 'صاحب الإعلان']);
    $this->actingAs($this->moderator);
});

// ---------------------------------------------------------------------- access

it('lets moderators and admins in and keeps regular users out', function () {
    $this->get('/admin/listings')->assertOk();
    $this->get('/admin/reports')->assertOk();

    $this->actingAs(User::factory()->admin()->create())->get('/admin/listings')->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin/listings')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/admin/reports')->assertForbidden();
});

it('does not offer creating listings in the panel', function () {
    expect(ListingResource::canCreate())->toBeFalse();
    $this->get('/admin/listings/create')->assertNotFound();
});

// ------------------------------------------------------------------ the queue

it('shows the pending queue by default and other statuses through the filter', function () {
    $pending = Listing::factory()->for($this->owner)->pending()->create();
    $active = Listing::factory()->for($this->owner)->create();
    $rejected = Listing::factory()->for($this->owner)->rejected()->create();

    Livewire::test(ListListings::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$active, $rejected])
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$pending, $rejected]);
});

it('shows the number of pending listings as a navigation badge', function () {
    expect(ListingResource::getNavigationBadge())->toBeNull();

    Listing::factory()->count(3)->pending()->create();
    Listing::factory()->create();

    expect(ListingResource::getNavigationBadge())->toBe('3');
});

it('renders a listing with all its fields and images', function () {
    Storage::fake('public');
    $tree = Fixtures::carsTree();
    $listing = Listing::factory()->for($this->owner)->pending()->titled('سيارة للمراجعة الكاملة', "وصف مفصل\nفي سطرين")->create(['category_id' => $tree['leaf']->id, 'phone' => '+201055556666']);
    $listing->fieldValues()->create(['category_field_id' => $tree['parent']->effectiveFields()->firstWhere('key', 'brand')->id, 'value' => 'كيا']);
    $file = Fixtures::image('a.jpg', 600, 400);
    $listing->addMedia($file->getRealPath())->preservingOriginal()->toMediaCollection(Listing::IMAGES);

    $this->get("/admin/listings/{$listing->id}")
        ->assertOk()
        ->assertSee('سيارة للمراجعة الكاملة')
        ->assertSee('صاحب الإعلان')
        ->assertSee('+201055556666')
        ->assertSee('كيا')
        ->assertSee('الماركة')
        ->assertSee(__('app.listing.statuses.pending'))
        ->assertSee($listing->getFirstMedia(Listing::IMAGES)->getUrl('medium'), false);
});

// -------------------------------------------------------------------- approve

it('approves a listing: active, published, new expiry, owner notified', function () {
    $listing = Listing::factory()->for($this->owner)->pending()->create();

    Livewire::test(ListListings::class)
        ->callAction(TestAction::make('approve')->table($listing))
        ->assertNotified();

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::Active)
        ->and($listing->published_at)->not->toBeNull()
        ->and((int) $listing->expires_at->diffInDays(now(), true))->toBeGreaterThanOrEqual(29)
        ->and($this->owner->notifications()->sole()->type)->toBe(ListingApproved::class);

    // ... and it is now public
    auth()->logout();
    $this->get($listing->url())->assertOk();
});

it('only offers approval for pending or rejected listings', function () {
    $active = Listing::factory()->for($this->owner)->create();
    $rejected = Listing::factory()->for($this->owner)->rejected()->create();

    Livewire::test(ListListings::class)
        ->filterTable('status', null)
        ->assertActionHidden(TestAction::make('approve')->table($active))
        ->assertActionVisible(TestAction::make('approve')->table($rejected));
});

it('bulk approves the selected pending listings', function () {
    $listings = Listing::factory()->count(3)->for($this->owner)->pending()->create();
    $untouched = Listing::factory()->for($this->owner)->pending()->create();

    Livewire::test(ListListings::class)
        ->selectTableRecords($listings->pluck('id')->all())
        ->callAction(TestAction::make('bulk_approve')->table()->bulk())
        ->assertNotified();

    expect(Listing::where('status', 'active')->count())->toBe(3)
        ->and($untouched->fresh()->status)->toBe(ListingStatus::Pending)
        ->and($this->owner->notifications()->count())->toBe(3);
});

// --------------------------------------------------------------------- reject

it('requires a reason to reject, stores it and notifies the owner with it', function () {
    $listing = Listing::factory()->for($this->owner)->pending()->create();

    Livewire::test(ListListings::class)
        ->callAction(TestAction::make('reject')->table($listing), ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($listing->fresh()->status)->toBe(ListingStatus::Pending);

    Livewire::test(ListListings::class)
        ->callAction(TestAction::make('reject')->table($listing), ['reason' => 'الصور لا تُظهر السلعة'])
        ->assertHasNoFormErrors();

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::Rejected)
        ->and($listing->rejection_reason)->toBe('الصور لا تُظهر السلعة')
        ->and($this->owner->notifications()->sole()->type)->toBe(ListingRejected::class)
        ->and($this->owner->notifications()->sole()->data['message'])->toContain('الصور لا تُظهر السلعة');

    // the owner can read the reason on their dashboard
    $this->actingAs($this->owner)->get('/dashboard?status=rejected')->assertSee('الصور لا تُظهر السلعة');
});

it('can take down an active listing by rejecting it', function () {
    $listing = Listing::factory()->for($this->owner)->create();

    Livewire::test(ListListings::class)
        ->filterTable('status', 'active')
        ->callAction(TestAction::make('reject')->table($listing), ['reason' => 'مخالف للسياسة']);

    expect($listing->fresh()->status)->toBe(ListingStatus::Rejected);
    auth()->logout();
    $this->get($listing->url())->assertNotFound();
});

// ----------------------------------------------------------- feature and delete

it('features a listing until a date and can clear it', function () {
    $listing = Listing::factory()->for($this->owner)->pending()->create();
    $until = now()->addDays(10)->startOfHour();

    Livewire::test(ListListings::class)
        ->callAction(TestAction::make('feature')->table($listing), ['featured_until' => $until->toDateTimeString()])
        ->assertHasNoFormErrors();

    expect($listing->fresh()->featured_until->toDateTimeString())->toBe($until->toDateTimeString())
        ->and($listing->fresh()->isFeatured())->toBeTrue();

    Livewire::test(ListListings::class)
        ->callAction(TestAction::make('feature')->table($listing), ['featured_until' => null]);

    expect($listing->fresh()->featured_until)->toBeNull();
});

it('soft deletes a listing', function () {
    $listing = Listing::factory()->for($this->owner)->pending()->create();

    Livewire::test(ListListings::class)
        ->callAction(TestAction::make('delete')->table($listing));

    expect(Listing::count())->toBe(0)->and(Listing::withTrashed()->count())->toBe(1);
});

it('has the moderation actions on the listing view page too', function () {
    $listing = Listing::factory()->for($this->owner)->pending()->create();

    Livewire::test(ViewListing::class, ['record' => $listing->getRouteKey()])
        ->assertActionVisible('approve')
        ->assertActionVisible('reject')
        ->assertActionVisible('feature')
        ->callAction('approve');

    expect($listing->fresh()->status)->toBe(ListingStatus::Active);
});

// -------------------------------------------------------------------- reports

it('lists open reports by default', function () {
    $listing = Listing::factory()->for($this->owner)->create();
    $open = Report::create(['listing_id' => $listing->id, 'user_id' => User::factory()->create()->id, 'reason' => 'scam']);
    $done = Report::create(['listing_id' => $listing->id, 'user_id' => User::factory()->create()->id, 'reason' => 'sold', 'status' => 'resolved']);

    Livewire::test(ListReports::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$done])
        ->filterTable('status', 'resolved')
        ->assertCanSeeTableRecords([$done]);
});

it('resolves or dismisses a report and records who handled it', function () {
    $listing = Listing::factory()->for($this->owner)->create();
    $one = Report::create(['listing_id' => $listing->id, 'user_id' => User::factory()->create()->id, 'reason' => 'scam']);
    $two = Report::create(['listing_id' => $listing->id, 'user_id' => User::factory()->create()->id, 'reason' => 'other', 'note' => 'ملاحظة']);

    Livewire::test(ListReports::class)
        ->callAction(TestAction::make('resolve')->table($one))
        ->callAction(TestAction::make('dismiss')->table($two));

    expect($one->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and($one->fresh()->handled_by)->toBe($this->moderator->id)
        ->and($two->fresh()->status)->toBe(ReportStatus::Dismissed)
        ->and($two->fresh()->handled_by)->toBe($this->moderator->id)
        ->and($listing->fresh()->status)->toBe(ListingStatus::Active);   // the listing itself is untouched
});

it('removes the reported listing and closes all its open reports', function () {
    $listing = Listing::factory()->for($this->owner)->create();
    $other = Listing::factory()->create();
    $target = Report::create(['listing_id' => $listing->id, 'user_id' => User::factory()->create()->id, 'reason' => 'scam']);
    $sibling = Report::create(['listing_id' => $listing->id, 'user_id' => User::factory()->create()->id, 'reason' => 'duplicate']);
    $unrelated = Report::create(['listing_id' => $other->id, 'user_id' => User::factory()->create()->id, 'reason' => 'scam']);

    Livewire::test(ListReports::class)
        ->callAction(TestAction::make('remove_listing')->table($target));

    expect(Listing::find($listing->id))->toBeNull()
        ->and($target->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and($sibling->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and($unrelated->fresh()->status)->toBe(ReportStatus::Open)
        ->and(Listing::find($other->id))->not->toBeNull();
});

// --------------------------------------------------------------------- widget

it('shows pending listings and open reports on the panel dashboard', function () {
    Listing::factory()->count(2)->pending()->create();
    $listing = Listing::factory()->create();
    Report::create(['listing_id' => $listing->id, 'user_id' => User::factory()->create()->id, 'reason' => 'scam']);

    $this->get('/admin')
        ->assertOk()
        ->assertSee(__('app.admin.pending_listings'))
        ->assertSee(__('app.admin.open_reports'));
});
