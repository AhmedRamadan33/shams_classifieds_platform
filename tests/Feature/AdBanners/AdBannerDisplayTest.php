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
