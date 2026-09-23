<?php

declare(strict_types=1);

use App\Actions\ApproveAdBanner;
use App\Enums\AdBannerStatus;
use App\Filament\Resources\AdBanners\AdBannerResource;
use App\Filament\Resources\AdBanners\Pages\ListAdBanners;
use App\Filament\Resources\AdPackages\Pages\CreateAdPackage;
use App\Filament\Resources\AdPackages\Pages\ListAdPackages;
use App\Filament\Resources\HeroSlides\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlides\Pages\ListHeroSlides;
use App\Models\AdBanner;
use App\Models\AdPackage;
use App\Models\HeroSlide;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
    $this->moderator = User::factory()->moderator()->create();
});

it('lets an admin manage ad packages but hides them from moderators', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/ad-packages')->assertOk();

    $this->actingAs($this->moderator);
    $this->get('/admin/ad-packages')->assertForbidden();
});

it('creates an ad package from the admin panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateAdPackage::class)
        ->fillForm(['name' => '7 أيام - الرئيسية', 'placement' => 'home_top', 'duration_days' => 7, 'price' => 100, 'sort_order' => 1, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AdPackage::where('name', '7 أيام - الرئيسية')->exists())->toBeTrue();
});

it('lets an admin manage hero slides but hides them from moderators', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/hero-slides')->assertOk();

    $this->actingAs($this->moderator);
    $this->get('/admin/hero-slides')->assertForbidden();
});

it('creates a hero slide with an uploaded image from the admin panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateHeroSlide::class)
        ->fillForm([
            'image_upload' => UploadedFile::fake()->image('slide.jpg', 1920, 800),
            'title' => 'عرض العيد',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $slide = HeroSlide::where('title', 'عرض العيد')->sole();
    expect($slide->hasMedia(HeroSlide::IMAGE))->toBeTrue();
});

it('lists hero slides ordered by sort_order', function () {
    $this->actingAs($this->admin);
    $second = HeroSlide::factory()->create(['sort_order' => 2]);
    $first = HeroSlide::factory()->create(['sort_order' => 1]);

    Livewire::test(ListHeroSlides::class)->assertCanSeeTableRecords([$first, $second], inOrder: true);
});

it('lets moderators and admins into ad banner moderation, and keeps regular users out', function () {
    $this->actingAs($this->moderator)->get('/admin/ad-banners')->assertOk();
    $this->actingAs($this->admin)->get('/admin/ad-banners')->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin/ad-banners')->assertForbidden();
});

it('does not offer creating ad banners in the panel', function () {
    expect(AdBannerResource::canCreate())->toBeFalse();
    $this->actingAs($this->admin)->get('/admin/ad-banners/create')->assertNotFound();
});

it('shows only pending and rejected banners as approvable', function () {
    $this->actingAs($this->moderator);
    $pending = AdBanner::factory()->create();
    $approved = AdBanner::factory()->approved()->create();

    Livewire::test(ListAdBanners::class)
        ->assertActionVisible(TestAction::make('approve')->table($pending))
        ->assertActionHidden(TestAction::make('approve')->table($approved));
});

it('approves a banner from the admin panel', function () {
    $this->actingAs($this->moderator);
    $banner = AdBanner::factory()->create();

    Livewire::test(ListAdBanners::class)
        ->callAction(TestAction::make('approve')->table($banner))
        ->assertNotified();

    expect($banner->fresh()->status)->toBe(AdBannerStatus::Approved);
});

it('rejects a banner from the admin panel with a reason', function () {
    $this->actingAs($this->moderator);
    $banner = AdBanner::factory()->create();

    Livewire::test(ListAdBanners::class)
        ->callAction(TestAction::make('reject')->table($banner), ['reason' => 'الصورة غير واضحة'])
        ->assertNotified();

    $banner->refresh();
    expect($banner->status)->toBe(AdBannerStatus::Rejected)
        ->and($banner->rejection_reason)->toBe('الصورة غير واضحة');
});

it('shows the number of pending banners as a navigation badge', function () {
    expect(AdBannerResource::getNavigationBadge())->toBeNull();

    AdBanner::factory()->count(2)->create();
    AdBanner::factory()->approved()->create();

    expect(AdBannerResource::getNavigationBadge())->toBe('2');
});

it('lists ad packages with their placement', function () {
    $this->actingAs($this->admin);
    $package = AdPackage::factory()->create(['placement' => 'home_top']);

    Livewire::test(ListAdPackages::class)
        ->assertCanSeeTableRecords([$package])
        ->assertSee(__('app.ad_banners.placements.home_top'));
});

it('approves a banner and its owner can then be routed to pay for it', function () {
    $owner = User::factory()->create();
    $banner = AdBanner::factory()->for($owner)->create();

    app(ApproveAdBanner::class)($banner);

    $this->actingAs($owner)->get(route('ad-banners.purchase', $banner))->assertOk();
});
