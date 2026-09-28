<?php

declare(strict_types=1);

use App\Models\AdBanner;
use App\Models\Category;
use App\Models\Listing;

it('shows nothing on the home page when there are no active home banners', function () {
    $this->get('/')->assertOk()->assertDontSee(__('app.ad_banners.sponsored_label'));
});

it('shows an active home_top banner on the home page, but not a banner for another placement', function () {
    $home = AdBanner::factory()->active()->create(['placement' => 'home_top', 'title' => 'بانر الرئيسية']);
    $sidebar = AdBanner::factory()->active()->create(['placement' => 'search_sidebar', 'title' => 'بانر الشريط الجانبي']);

    $response = $this->get('/');
    $response->assertSee(__('app.ad_banners.sponsored_label'));
    $response->assertSee(route('ad-banners.click', $home));
    $response->assertDontSee(route('ad-banners.click', $sidebar));
});

it('shows every active banner of a placement as the slides of one slider', function () {
    $banners = AdBanner::factory()->active()->count(3)->create(['placement' => 'home_top']);
    $other = AdBanner::factory()->active()->create(['placement' => 'search_sidebar']);

    $html = $this->get('/')->assertOk()->getContent();

    foreach ($banners as $banner) {
        expect($html)->toContain(route('ad-banners.click', $banner));
    }

    expect($html)->not->toContain(route('ad-banners.click', $other))
        ->and(substr_count($html, 'aria-roledescription="carousel"'))->toBe(1)
        ->and(substr_count($html, 'aria-roledescription="slide"'))->toBe(3)
        ->and($html)->toContain(__('app.ad_banners.next'))
        ->and($html)->toContain(__('app.ad_banners.previous'));
});

it('shows a single banner as a plain block without slider controls', function () {
    $banner = AdBanner::factory()->active()->create(['placement' => 'home_top']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain(route('ad-banners.click', $banner))
        ->and($html)->not->toContain(__('app.ad_banners.next'))
        ->and($html)->not->toContain('x-show="index');
});

it('puts the slider of a sidebar placement on the search page too', function () {
    $banners = AdBanner::factory()->active()->count(2)->create(['placement' => 'search_sidebar']);

    $html = $this->get('/search')->assertOk()->getContent();

    foreach ($banners as $banner) {
        expect($html)->toContain(route('ad-banners.click', $banner));
    }
});

it('caps a slider at ten banners', function () {
    AdBanner::factory()->active()->count(12)->create(['placement' => 'home_top']);

    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'aria-roledescription="slide"'))->toBe(10);
});

it('places the home banners at the end of the page, after the call to action and before the footer', function () {
    $banner = AdBanner::factory()->active()->create(['placement' => 'home_top']);

    $this->get('/')->assertOk()->assertSeeInOrder([
        __('app.home.categories_title'),
        __('app.home.cta_title'),
        route('ad-banners.click', $banner),
        '<footer',
    ], false);
});
it('never shows a pending, rejected or expired banner anywhere', function () {
    $pending = AdBanner::factory()->create(['placement' => 'home_top']);
    $rejected = AdBanner::factory()->rejected()->create(['placement' => 'home_top']);
    $expired = AdBanner::factory()->expired()->create(['placement' => 'home_top']);

    $response = $this->get('/');
    foreach ([$pending, $rejected, $expired] as $banner) {
        $response->assertDontSee(route('ad-banners.click', $banner));
    }
});

it('shows the search_sidebar banner on a category page', function () {
    $category = Category::factory()->create();
    $banner = AdBanner::factory()->active()->create(['placement' => 'search_sidebar']);

    $this->get(route('categories.show', $category->slug))
        ->assertSee(route('ad-banners.click', $banner));
});

it('shows the listing_sidebar banner on a listing page', function () {
    $listing = Listing::factory()->create();
    $banner = AdBanner::factory()->active()->create(['placement' => 'listing_sidebar']);

    $this->get($listing->url())
        ->assertSee(route('ad-banners.click', $banner));
});

it('shows a listing banner while its listing is live, hides it whenever the listing stops being live, and brings it back when it is live again', function () {
    $listing = Listing::factory()->create();
    $banner = AdBanner::factory()->targetingListing($listing)->active()->create(['placement' => 'home_top']);
    $url = route('ad-banners.click', $banner);

    $this->get('/')->assertSee($url);

    $listing->update(['status' => 'sold']);
    $this->get('/')->assertDontSee($url);

    $listing->update(['status' => 'expired']);
    $this->get('/')->assertDontSee($url);

    $listing->update(['status' => 'active', 'expires_at' => now()->subMinute()]);
    $this->get('/')->assertDontSee($url);

    $listing->update(['expires_at' => now()->addDays(10)]);
    $this->get('/')->assertSee($url);

    $listing->delete();
    $this->get('/')->assertDontSee($url);

    $listing->restore();
    $this->get('/')->assertSee($url);

    $listing->user->update(['is_banned' => true]);
    $this->get('/')->assertDontSee($url);
});

it('opens a listing banner in the same tab and an external one in a new tab', function () {
    $listing = Listing::factory()->create();
    AdBanner::factory()->targetingListing($listing)->active()->create(['placement' => 'home_top']);

    $this->get('/')->assertDontSee('target="_blank"', false);

    AdBanner::query()->delete();
    AdBanner::factory()->active()->create(['placement' => 'home_top']);

    $this->get('/')->assertSee('target="_blank"', false);
});
