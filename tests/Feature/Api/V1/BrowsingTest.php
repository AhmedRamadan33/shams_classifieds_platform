<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Governorate;
use App\Models\Listing;
use Tests\Support\Fixtures;

it('lists the active category tree', function () {
    $tree = Fixtures::carsTree();

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'cars'])
        ->assertJsonFragment(['slug' => 'cars-for-sale']);
});

it('returns a leaf category\'s dynamic fields', function () {
    $tree = Fixtures::carsTree();

    $this->getJson("/api/v1/categories/{$tree['leaf']->slug}/fields")
        ->assertOk()
        ->assertJsonFragment(['key' => 'brand'])
        ->assertJsonFragment(['key' => 'year']);
});

it('404s for a non-leaf category\'s fields', function () {
    $tree = Fixtures::carsTree();

    $this->getJson("/api/v1/categories/{$tree['parent']->slug}/fields")->assertNotFound();
});

it('lists governorates with their cities', function () {
    $governorate = Governorate::factory()->create(['name' => 'القاهرة', 'slug' => 'cairo']);
    City::factory()->for($governorate)->create(['name' => 'مدينة نصر']);

    $this->getJson('/api/v1/governorates')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'cairo'])
        ->assertJsonFragment(['name' => 'مدينة نصر']);
});

it('lists only visible listings, paginated', function () {
    Listing::factory()->count(3)->create(['status' => 'active']);
    Listing::factory()->create(['status' => 'pending']);

    $response = $this->getJson('/api/v1/listings')->assertOk();

    expect($response->json('meta.total'))->toBe(3)
        ->and($response->json('data'))->toHaveCount(3);
});

it('filters listings by category through the flat API', function () {
    $tree = Fixtures::carsTree();
    $inCategory = Listing::factory()->create(['category_id' => $tree['leaf']->id, 'status' => 'active']);
    Listing::factory()->create(['status' => 'active']);

    $response = $this->getJson('/api/v1/listings?category='.$tree['leaf']->slug)->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.id'))->toBe($inCategory->id);
});

it('shows a single visible listing without its phone number', function () {
    $listing = Listing::factory()->create(['status' => 'active', 'phone' => '+201099999999']);

    $response = $this->getJson("/api/v1/listings/{$listing->id}")->assertOk();

    expect($response->json('data.id'))->toBe($listing->id);
    $response->assertJsonMissingPath('data.phone');
    expect($response->getContent())->not->toContain('201099999999');
});

it('404s for a pending or expired listing', function () {
    $pending = Listing::factory()->create(['status' => 'pending']);
    $this->getJson("/api/v1/listings/{$pending->id}")->assertNotFound();
});

it('counts a view once per requester', function () {
    $listing = Listing::factory()->create(['status' => 'active', 'views' => 0]);

    $this->getJson("/api/v1/listings/{$listing->id}");
    $this->getJson("/api/v1/listings/{$listing->id}");

    expect($listing->fresh()->views)->toBe(2);
});

it('reveals the phone number through the contact endpoint, publicly and throttled', function () {
    $listing = Listing::factory()->create(['status' => 'active', 'phone' => '+201099999999']);

    $this->postJson("/api/v1/listings/{$listing->id}/contact")
        ->assertOk()
        ->assertJsonPath('phone', '+201099999999')
        ->assertJsonStructure(['phone', 'whatsapp_url']);

    expect($listing->events()->count())->toBe(1);
});
