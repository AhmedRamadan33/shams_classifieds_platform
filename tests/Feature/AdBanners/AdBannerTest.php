<?php

declare(strict_types=1);

use App\Actions\ApproveAdBanner;
use App\Actions\RejectAdBanner;
use App\Enums\AdBannerStatus;
use App\Models\AdBanner;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\AdBannerApproved;
use App\Notifications\AdBannerRejected;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

it('requires authentication to submit a banner', function () {
    $this->get(route('ad-banners.create'))->assertRedirect(route('login'));
    $this->post(route('ad-banners.store'))->assertRedirect(route('login'));
});

it('lets a signed-in user submit a banner for moderation', function () {
    $this->actingAs($this->user)->post(route('ad-banners.store'), [
        'placement' => 'home_top',
        'title' => 'عرض رمضان',
        'target_type' => 'url',
        'target_url' => 'https://example.com/ramadan',
        'image' => Fixtures::image(),
    ])->assertRedirect(route('ad-banners.index'));

    $banner = AdBanner::sole();
    expect($banner->user_id)->toBe($this->user->id)
        ->and($banner->placement->value)->toBe('home_top')
        ->and($banner->title)->toBe('عرض رمضان')
        ->and($banner->target_url)->toBe('https://example.com/ramadan')
        ->and($banner->status)->toBe(AdBannerStatus::Pending)
        ->and($banner->hasMedia(AdBanner::IMAGE))->toBeTrue();
});

it('validates the submission', function () {
    $this->actingAs($this->user)->post(route('ad-banners.store'), [])
        ->assertSessionHasErrors(['placement', 'target_url', 'image']);

    expect(AdBanner::count())->toBe(0);
});

it('lists only the current user\'s own banners', function () {
    $mine = AdBanner::factory()->for($this->user)->create();
    AdBanner::factory()->create();

    $this->actingAs($this->user)->get(route('ad-banners.index'))
        ->assertOk()
        ->assertSee($mine->title);
});

it('approves a pending banner and notifies the advertiser', function () {
    Notification::fake();
    $banner = AdBanner::factory()->for($this->user)->create();

    app(ApproveAdBanner::class)($banner);

    expect($banner->fresh()->status)->toBe(AdBannerStatus::Approved);
    Notification::assertSentTo($this->user, AdBannerApproved::class);
});

it('rejects a pending banner with a reason and notifies the advertiser', function () {
    Notification::fake();
    $banner = AdBanner::factory()->for($this->user)->create();

    app(RejectAdBanner::class)($banner, 'الصورة منخفضة الجودة');

    $banner->refresh();
    expect($banner->status)->toBe(AdBannerStatus::Rejected)
        ->and($banner->rejection_reason)->toBe('الصورة منخفضة الجودة');
    Notification::assertSentTo($this->user, AdBannerRejected::class);
});

it('shows the rejection reason to the owner on their banners page', function () {
    $banner = AdBanner::factory()->for($this->user)->rejected()->create();

    $this->actingAs($this->user)->get(route('ad-banners.index'))
        ->assertSee($banner->rejection_reason);
});

it('only accepts http and https target urls', function (string $url) {
    $this->actingAs($this->user)->post(route('ad-banners.store'), [
        'placement' => 'home_top',
        'target_type' => 'url',
        'target_url' => $url,
        'image' => Fixtures::image(),
    ])->assertSessionHasErrors('target_url');

    expect(AdBanner::count())->toBe(0);
})->with(['javascript:alert(1)', 'ftp://example.com/file', 'data:text/html;base64,PGh0bWw+', 'file:///etc/passwd']);

it('lets an advertiser point a banner at one of their own live listings', function () {
    $listing = Listing::factory()->for($this->user)->create();

    $this->actingAs($this->user)->post(route('ad-banners.store'), [
        'placement' => 'search_sidebar',
        'target_type' => 'listing',
        'listing_id' => $listing->id,
        'image' => Fixtures::image(),
    ])->assertRedirect(route('ad-banners.index'));

    $banner = AdBanner::sole();
    expect($banner->listing_id)->toBe($listing->id)
        ->and($banner->target_url)->toBeNull()
        ->and($banner->targetsListing())->toBeTrue()
        ->and($banner->status)->toBe(AdBannerStatus::Pending);
});

it('drops the other target when a listing is chosen, and the listing when a url is chosen', function () {
    $listing = Listing::factory()->for($this->user)->create();

    $this->actingAs($this->user)->post(route('ad-banners.store'), [
        'placement' => 'home_top',
        'target_type' => 'listing',
        'listing_id' => $listing->id,
        'target_url' => 'https://example.com/ignored',
        'image' => Fixtures::image(),
    ]);

    $this->actingAs($this->user)->post(route('ad-banners.store'), [
        'placement' => 'home_top',
        'target_type' => 'url',
        'target_url' => 'https://example.com/kept',
        'listing_id' => $listing->id,
        'image' => Fixtures::image(),
    ]);

    [$byListing, $byUrl] = AdBanner::orderBy('id')->get()->all();
    expect($byListing->target_url)->toBeNull()->and($byListing->listing_id)->toBe($listing->id)
        ->and($byUrl->target_url)->toBe('https://example.com/kept')->and($byUrl->listing_id)->toBeNull();
});

it('rejects a listing that is not the advertiser\'s own or not live', function (string $case) {
    $listing = match ($case) {
        'someone else' => Listing::factory()->create(),
        'pending' => Listing::factory()->for($this->user)->pending()->create(),
        'rejected' => Listing::factory()->for($this->user)->rejected()->create(),
        'sold' => Listing::factory()->for($this->user)->sold()->create(),
        'expired by date' => Listing::factory()->for($this->user)->create(['expires_at' => now()->subDay()]),
        'deleted' => tap(Listing::factory()->for($this->user)->create(), fn (Listing $l) => $l->delete()),
    };

    $this->actingAs($this->user)->post(route('ad-banners.store'), [
        'placement' => 'home_top',
        'target_type' => 'listing',
        'listing_id' => $listing->id,
        'image' => Fixtures::image(),
    ])->assertSessionHasErrors('listing_id');

    expect(AdBanner::count())->toBe(0);
})->with(['someone else', 'pending', 'rejected', 'sold', 'expired by date', 'deleted']);

it('requires the field that matches the chosen target type', function () {
    $this->actingAs($this->user)->post(route('ad-banners.store'), ['placement' => 'home_top', 'target_type' => 'listing', 'image' => Fixtures::image()])
        ->assertSessionHasErrors('listing_id');

    $this->actingAs($this->user)->post(route('ad-banners.store'), ['placement' => 'home_top', 'target_type' => 'url', 'image' => Fixtures::image()])
        ->assertSessionHasErrors('target_url');

    $this->actingAs($this->user)->post(route('ad-banners.store'), ['placement' => 'home_top', 'target_url' => 'https://example.com', 'image' => Fixtures::image()])
        ->assertSessionHasErrors('target_type');

    expect(AdBanner::count())->toBe(0);
});

it('offers only the advertiser\'s own live listings in the form', function () {
    Listing::factory()->for($this->user)->titled('إعلاني الساري', 'وصف')->create();
    Listing::factory()->for($this->user)->sold()->titled('إعلاني المباع', 'وصف')->create();
    Listing::factory()->titled('إعلان شخص آخر', 'وصف')->create();

    $this->actingAs($this->user)->get(route('ad-banners.create'))
        ->assertOk()
        ->assertSee('إعلاني الساري')
        ->assertDontSee('إعلاني المباع')
        ->assertDontSee('إعلان شخص آخر');
});

it('lists listing banners without lazy loading, and flags the ones whose listing is no longer live', function () {
    $live = Listing::factory()->for($this->user)->titled('إعلان ساري للبانر', 'وصف')->create();
    $sold = Listing::factory()->for($this->user)->sold()->titled('إعلان مباع للبانر', 'وصف')->create();
    $gone = Listing::factory()->for($this->user)->titled('إعلان محذوف للبانر', 'وصف')->create();

    AdBanner::factory()->targetingListing($live)->active()->create();
    AdBanner::factory()->targetingListing($sold)->active()->create();
    AdBanner::factory()->targetingListing($gone)->active()->create();
    $gone->forceDelete();

    $response = $this->actingAs($this->user)->get(route('ad-banners.index'))
        ->assertOk()
        ->assertSee('إعلان ساري للبانر')
        ->assertSee('إعلان مباع للبانر')
        ->assertSee(__('app.ad_banners.listing_removed'));

    expect(substr_count($response->getContent(), __('app.ad_banners.paused_listing_unavailable')))->toBe(2);
});

it('keeps the banner but detaches it when its listing is deleted for good', function () {
    $listing = Listing::factory()->for($this->user)->create();
    $banner = AdBanner::factory()->targetingListing($listing)->active()->create();

    $listing->forceDelete();

    $banner->refresh();
    expect($banner->exists)->toBeTrue()
        ->and($banner->listing_id)->toBeNull()
        ->and($banner->hasLiveTarget())->toBeFalse()
        ->and(AdBanner::query()->currentlyActive()->count())->toBe(0);
});
