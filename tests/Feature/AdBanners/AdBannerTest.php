<?php

declare(strict_types=1);

use App\Actions\ApproveAdBanner;
use App\Actions\RejectAdBanner;
use App\Enums\AdBannerStatus;
use App\Models\AdBanner;
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
