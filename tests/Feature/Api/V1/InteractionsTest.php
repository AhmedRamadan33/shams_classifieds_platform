<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ListingApproved;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

// -------------------------------------------------------------------- favorites

it('toggles a favorite and lists it back', function () {
    $listing = Listing::factory()->create(['status' => 'active']);

    $this->withToken($this->token)
        ->postJson("/api/v1/listings/{$listing->id}/favorite")
        ->assertOk()
        ->assertJson(['favorited' => true]);

    $this->withToken($this->token)->getJson('/api/v1/my/favorites')->assertOk()->assertJsonFragment(['id' => $listing->id]);

    $this->withToken($this->token)
        ->postJson("/api/v1/listings/{$listing->id}/favorite")
        ->assertOk()
        ->assertJson(['favorited' => false]);

    $this->withToken($this->token)->getJson('/api/v1/my/favorites')->assertOk()->assertJsonMissing(['id' => $listing->id]);
});

it('requires authentication to favorite a listing', function () {
    $listing = Listing::factory()->create(['status' => 'active']);

    $this->postJson("/api/v1/listings/{$listing->id}/favorite")->assertUnauthorized();
});

// ---------------------------------------------------------------------- reports

it('reports a listing once, refusing a second report and a report of your own listing', function () {
    $listing = Listing::factory()->create(['status' => 'active']);
    $own = Listing::factory()->create(['status' => 'active', 'user_id' => $this->user->id]);

    $this->withToken($this->token)
        ->postJson("/api/v1/listings/{$listing->id}/reports", ['reason' => 'scam'])
        ->assertCreated();

    expect(Report::count())->toBe(1);

    $this->withToken($this->token)
        ->postJson("/api/v1/listings/{$listing->id}/reports", ['reason' => 'duplicate'])
        ->assertStatus(409);

    $this->withToken($this->token)
        ->postJson("/api/v1/listings/{$own->id}/reports", ['reason' => 'scam'])
        ->assertStatus(422);

    expect(Report::count())->toBe(1);
});

// ---------------------------------------------------------------- notifications

it('lists notifications, marks them read, and can mark all as read', function () {
    $this->user->notify(new ListingApproved(Listing::factory()->create(['user_id' => $this->user->id])));

    $response = $this->withToken($this->token)->getJson('/api/v1/my/notifications')->assertOk();
    expect($response->json('meta.unread_count'))->toBe(1); // how many were unread before this call marked them read
    expect($this->user->notifications()->sole()->read_at)->not->toBeNull();

    // reading it again reports 0 new: this one is already marked read
    expect($this->withToken($this->token)->getJson('/api/v1/my/notifications')->json('meta.unread_count'))->toBe(0);

    $this->user->notify(new ListingApproved(Listing::factory()->create(['user_id' => $this->user->id])));
    $this->withToken($this->token)->postJson('/api/v1/my/notifications/read-all')->assertOk();

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('requires authentication for notifications', function () {
    $this->getJson('/api/v1/my/notifications')->assertUnauthorized();
});
