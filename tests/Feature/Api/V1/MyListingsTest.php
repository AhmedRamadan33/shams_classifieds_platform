<?php

declare(strict_types=1);

use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->tree = Fixtures::carsTree();
    $this->governorate = Governorate::factory()->create();
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('requires authentication for every /my/listings endpoint', function () {
    $this->getJson('/api/v1/my/listings')->assertUnauthorized();
    $this->postJson('/api/v1/my/listings', [])->assertUnauthorized();
});

it('creates a listing with uploaded images', function () {
    $payload = Fixtures::listingPayload($this->tree['leaf'], $this->governorate);
    $payload['images'] = [Fixtures::image(), Fixtures::image()];

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/my/listings', $payload)
        ->assertCreated();

    $listing = Listing::sole();
    expect($listing->user_id)->toBe($this->user->id)
        ->and($listing->title)->toBe($payload['title'])
        ->and($listing->status->value)->toBe('pending')
        ->and($listing->media()->count())->toBe(2);

    expect($response->json('data.images'))->toHaveCount(2);
});

it('validates a new listing the same way the website does', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/my/listings', ['title' => 'قصير'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'description', 'category_id', 'governorate_id', 'phone']);
});

it('lists only the caller\'s own listings', function () {
    Listing::factory()->create(['user_id' => $this->user->id]);
    Listing::factory()->create();

    $response = $this->withToken($this->token)->getJson('/api/v1/my/listings')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('shows, but forbids viewing someone else\'s listing', function () {
    $mine = Listing::factory()->create(['user_id' => $this->user->id]);
    $theirs = Listing::factory()->create();

    $this->withToken($this->token)->getJson("/api/v1/my/listings/{$mine->id}")->assertOk();
    $this->withToken($this->token)->getJson("/api/v1/my/listings/{$theirs->id}")->assertForbidden();
});

it('updates a listing, replacing images and sending it back to review', function () {
    $listing = Listing::factory()->create(['user_id' => $this->user->id, 'status' => 'active']);
    $listing->addMedia(Fixtures::image())->preservingOriginal()->toMediaCollection(Listing::IMAGES);
    $existingId = $listing->getMedia(Listing::IMAGES)->first()->id;

    $payload = Fixtures::listingPayload($this->tree['leaf'], $this->governorate, null, ['title' => 'عنوان محدث لإعلاني']);
    $payload['images'] = [Fixtures::image()];
    $payload['remove_images'] = [$existingId];

    $this->withToken($this->token)
        ->postJson("/api/v1/my/listings/{$listing->id}/update", $payload)
        ->assertOk()
        ->assertJsonPath('data.title', 'عنوان محدث لإعلاني');

    $listing->refresh();
    expect($listing->status->value)->toBe('pending')
        ->and($listing->media()->count())->toBe(1)
        ->and($listing->getMedia(Listing::IMAGES)->pluck('id'))->not->toContain($existingId);
});

it('forbids updating or deleting someone else\'s listing', function () {
    $theirs = Listing::factory()->create();
    $payload = Fixtures::listingPayload($this->tree['leaf'], $this->governorate);

    $this->withToken($this->token)->postJson("/api/v1/my/listings/{$theirs->id}/update", $payload)->assertForbidden();
    $this->withToken($this->token)->deleteJson("/api/v1/my/listings/{$theirs->id}")->assertForbidden();

    expect(Listing::find($theirs->id))->not->toBeNull();
});

it('deletes the caller\'s own listing', function () {
    $listing = Listing::factory()->create(['user_id' => $this->user->id]);

    $this->withToken($this->token)->deleteJson("/api/v1/my/listings/{$listing->id}")->assertOk();

    expect(Listing::find($listing->id))->toBeNull();
});

it('renews an expired listing and marks it sold', function () {
    $expired = Listing::factory()->create(['user_id' => $this->user->id, 'status' => 'expired', 'expires_at' => now()->subDay()]);

    $this->withToken($this->token)
        ->postJson("/api/v1/my/listings/{$expired->id}/renew")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $active = Listing::factory()->create(['user_id' => $this->user->id, 'status' => 'active']);

    $this->withToken($this->token)
        ->postJson("/api/v1/my/listings/{$active->id}/sold")
        ->assertOk()
        ->assertJsonPath('data.status', 'sold');
});

it('refuses to renew a listing that is not eligible yet', function () {
    $listing = Listing::factory()->create(['user_id' => $this->user->id, 'status' => 'active', 'expires_at' => now()->addDays(20)]);

    $this->withToken($this->token)
        ->postJson("/api/v1/my/listings/{$listing->id}/renew")
        ->assertStatus(422);
});

it('enforces the daily listing limit through the API', function () {
    config(['classifieds.daily_listing_limit' => 1]);
    Listing::factory()->create(['user_id' => $this->user->id]);

    $this->withToken($this->token)
        ->postJson('/api/v1/my/listings', Fixtures::listingPayload($this->tree['leaf'], $this->governorate, null, ['title' => 'إعلان ثانٍ زائد عن الحد']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});
