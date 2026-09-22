<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->create();
    $this->buyer = User::factory()->create();
    $this->buyerToken = $this->buyer->createToken('test')->plainTextToken;
    $this->listing = Listing::factory()->create(['user_id' => $this->seller->id, 'status' => 'active']);
});

it('starts a conversation with an initial message', function () {
    $this->withToken($this->buyerToken)
        ->postJson("/api/v1/listings/{$this->listing->id}/messages", ['body' => 'هل السعر نهائي؟'])
        ->assertCreated()
        ->assertJsonPath('data.other_participant.id', $this->seller->id);

    $conversation = Conversation::sole();
    expect($conversation->messages()->count())->toBe(1);
});

it('refuses to let a seller message their own listing', function () {
    $sellerToken = $this->seller->createToken('test')->plainTextToken;

    $this->withToken($sellerToken)
        ->postJson("/api/v1/listings/{$this->listing->id}/messages", [])
        ->assertStatus(422);
});

it('lists conversations and shows/replies within a thread', function () {
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
    $conversation->messages()->create(['sender_id' => $this->seller->id, 'body' => 'مرحباً، نعم متاحة']);

    $this->withToken($this->buyerToken)->getJson('/api/v1/my/messages')->assertOk()->assertJsonFragment(['id' => $conversation->id]);

    $this->withToken($this->buyerToken)
        ->getJson("/api/v1/my/messages/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'مرحباً، نعم متاحة')
        ->assertJsonPath('messages.0.mine', false);

    $this->withToken($this->buyerToken)
        ->postJson("/api/v1/my/messages/{$conversation->id}", ['body' => 'ممتاز، أين يمكن المعاينة؟'])
        ->assertCreated();

    expect($conversation->messages()->count())->toBe(2);
});

it('forbids a stranger from reading or replying to someone else\'s conversation', function () {
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
    $stranger = User::factory()->create();
    $strangerToken = $stranger->createToken('test')->plainTextToken;

    $this->withToken($strangerToken)->getJson("/api/v1/my/messages/{$conversation->id}")->assertForbidden();
    $this->withToken($strangerToken)->postJson("/api/v1/my/messages/{$conversation->id}", ['body' => 'x'])->assertForbidden();
});

it('requires authentication for messaging', function () {
    $this->postJson("/api/v1/listings/{$this->listing->id}/messages", ['body' => 'hi'])->assertUnauthorized();
    $this->getJson('/api/v1/my/messages')->assertUnauthorized();
});
