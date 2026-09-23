<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seller = User::factory()->create(['name' => 'صاحب الإعلان']);
    $this->listing = Listing::factory()->create(['user_id' => $this->seller->id, 'status' => 'active']);
    $this->buyer = User::factory()->create(['name' => 'مشتري']);
});

it('starts a conversation from the listing page and reuses it on a second message', function () {
    Notification::fake();

    $this->actingAs($this->buyer)
        ->post(route('listings.message', $this->listing), ['body' => 'مرحباً، هل السعر قابل للتفاوض؟'])
        ->assertRedirect();

    $conversation = Conversation::sole();
    expect($conversation->buyer_id)->toBe($this->buyer->id)
        ->and($conversation->seller_id)->toBe($this->seller->id)
        ->and($conversation->listing_id)->toBe($this->listing->id)
        ->and(Message::count())->toBe(1)
        ->and(Message::sole()->body)->toBe('مرحباً، هل السعر قابل للتفاوض؟');

    Notification::assertSentTo($this->seller, NewMessageReceived::class);

    $this->actingAs($this->buyer)->post(route('listings.message', $this->listing), ['body' => 'رسالة ثانية']);

    expect(Conversation::count())->toBe(1)
        ->and(Message::count())->toBe(2);
});

it('shows the thread in order and marks the other participant\'s messages as read', function () {
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
    $m1 = $conversation->messages()->create(['sender_id' => $this->buyer->id, 'body' => 'رسالة 1']);
    $m2 = $conversation->messages()->create(['sender_id' => $this->seller->id, 'body' => 'رسالة 2']);

    expect($this->seller->unreadMessagesCount())->toBe(1);

    $this->actingAs($this->seller)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSeeInOrder(['رسالة 1', 'رسالة 2']);

    expect($m1->fresh()->read_at)->not->toBeNull()
        ->and($this->seller->fresh()->unreadMessagesCount())->toBe(0);
});

it('sends a reply through the thread and notifies the other participant', function () {
    Notification::fake();
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);

    $this->actingAs($this->seller)
        ->post(route('messages.store', $conversation), ['body' => 'أهلاً، نعم السعر قابل للتفاوض قليلاً.'])
        ->assertRedirect(route('messages.show', $conversation));

    expect($conversation->messages()->count())->toBe(1)
        ->and($conversation->fresh()->last_message_at)->not->toBeNull();

    Notification::assertSentTo($this->buyer, NewMessageReceived::class);
});

it('rejects an empty or oversized message', function () {
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);

    $this->actingAs($this->buyer)->post(route('messages.store', $conversation), ['body' => ''])->assertSessionHasErrors('body');
    $this->actingAs($this->buyer)->post(route('messages.store', $conversation), ['body' => str_repeat('ا', 2001)])->assertSessionHasErrors('body');

    expect(Message::count())->toBe(0);
});

it('rejects a message containing a blocked word', function () {
    config(['classifieds.blocked_words' => ['كلمة-محظورة']]);
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);

    $this->actingAs($this->buyer)
        ->post(route('messages.store', $conversation), ['body' => 'هذه كلمة-محظورة في الرسالة'])
        ->assertSessionHas('error', __('app.messages.blocked_content'));

    expect(Message::count())->toBe(0);
});

it('refuses to let a user message their own listing', function () {
    $this->actingAs($this->seller)
        ->post(route('listings.message', $this->listing), ['body' => 'مرحباً'])
        ->assertSessionHas('error', __('app.messages.own_listing'));

    expect(Conversation::count())->toBe(0);
});

it('refuses to start a conversation about an unavailable listing', function () {
    $this->listing->update(['status' => 'pending']);

    $this->actingAs($this->buyer)
        ->post(route('listings.message', $this->listing), ['body' => 'مرحباً'])
        ->assertSessionHas('error', __('app.messages.listing_unavailable'));

    expect(Conversation::count())->toBe(0);
});

it('forbids a stranger from viewing or replying to someone else\'s conversation', function () {
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('messages.show', $conversation))->assertForbidden();
    $this->actingAs($stranger)->post(route('messages.store', $conversation), ['body' => 'x'])->assertForbidden();
    $this->actingAs($stranger)->get(route('messages.poll', $conversation).'?after=0')->assertForbidden();
});

it('requires authentication to message a seller', function () {
    $this->post(route('listings.message', $this->listing), ['body' => 'مرحباً'])->assertRedirect(route('login'));
});

it('lists conversations in the inbox with an unread badge, newest activity first', function () {
    $older = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'last_message_at' => now()->subDay()]);
    $listing2 = Listing::factory()->create(['user_id' => $this->seller->id]);
    $newer = Conversation::create(['listing_id' => $listing2->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'last_message_at' => now()]);
    $newer->messages()->create(['sender_id' => $this->seller->id, 'body' => 'رسالة غير مقروءة']);

    $response = $this->actingAs($this->buyer)->get(route('messages.index'))->assertOk();
    $response->assertSeeInOrder([$listing2->title, $this->listing->title]);
});

it('polls for new messages after a given id and marks them read', function () {
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
    $first = $conversation->messages()->create(['sender_id' => $this->seller->id, 'body' => 'رسالة أولى']);

    $response = $this->actingAs($this->buyer)
        ->getJson(route('messages.poll', $conversation).'?after=0')
        ->assertOk();

    expect($response->json('messages'))->toHaveCount(1)
        ->and($response->json('messages.0.body'))->toBe('رسالة أولى')
        ->and($response->json('messages.0.mine'))->toBeFalse();

    expect($first->fresh()->read_at)->not->toBeNull();

    $this->actingAs($this->buyer)
        ->getJson(route('messages.poll', $conversation).'?after='.$first->id)
        ->assertOk()
        ->assertJson(['messages' => []]);
});

it('shows the unread messages badge in the header', function () {
    $conversation = Conversation::create(['listing_id' => $this->listing->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
    $conversation->messages()->create(['sender_id' => $this->buyer->id, 'body' => 'مرحباً']);

    $this->actingAs($this->seller)->get('/')->assertSee('data-testid="unread-messages-badge"', false)->assertSee('>1<', false);
});
