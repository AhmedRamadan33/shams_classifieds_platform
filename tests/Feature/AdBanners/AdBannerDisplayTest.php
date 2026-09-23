<?php

declare(strict_types=1);

use App\Models\AdBanner;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Listing;

it('shows nothing on the home page when there are no active hero slides or home banners', function () {
    $this->get('/')->assertOk()->assertDontSee(__('app.ad_banners.sponsored_label'));
});

it('shows the hero slider on the home page only when an active slide exists', function () {
    $slide = HeroSlide::factory()->create(['title' => 'عرض العيد', 'is_active' => false]);
    $this->get('/')->assertDontSee('عرض العيد');

    $slide->update(['is_active' => true]);
    $this->get('/')->assertSee('عرض العيد');
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
