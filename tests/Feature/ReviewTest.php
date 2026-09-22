<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->create(['name' => 'بائع تجريبي']);
    $this->reviewer = User::factory()->create();
});

it('leaves a review for a seller', function () {
    $this->actingAs($this->reviewer)
        ->post("/seller/{$this->seller->id}/reviews", ['rating' => 4, 'comment' => 'تعامل جيد جداً'])
        ->assertRedirect();

    $review = Review::sole();
    expect($review->reviewer_id)->toBe($this->reviewer->id)
        ->and($review->seller_id)->toBe($this->seller->id)
        ->and($review->rating)->toBe(4)
        ->and($review->comment)->toBe('تعامل جيد جداً')
        ->and($review->is_hidden)->toBeFalse();
});

it('updates the reviewer\'s existing review instead of creating a second one', function () {
    Review::factory()->create(['reviewer_id' => $this->reviewer->id, 'seller_id' => $this->seller->id, 'rating' => 2]);

    $this->actingAs($this->reviewer)->post("/seller/{$this->seller->id}/reviews", ['rating' => 5, 'comment' => 'تحسّن كثيراً']);

    expect(Review::count())->toBe(1);
    $review = Review::sole();
    expect($review->rating)->toBe(5)->and($review->comment)->toBe('تحسّن كثيراً');
});

it('validates the rating', function (mixed $rating) {
    $this->actingAs($this->reviewer)
        ->post("/seller/{$this->seller->id}/reviews", ['rating' => $rating])
        ->assertSessionHasErrors('rating');

    expect(Review::count())->toBe(0);
})->with(['missing' => [null], 'zero' => [0], 'too high' => [6], 'not a number' => ['x']]);

it('refuses to let a user review themselves', function () {
    $this->actingAs($this->seller)
        ->post("/seller/{$this->seller->id}/reviews", ['rating' => 5])
        ->assertSessionHas('error', __('app.reviews.own_profile'));

    expect(Review::count())->toBe(0);
});

it('requires authentication to leave a review', function () {
    $this->post("/seller/{$this->seller->id}/reviews", ['rating' => 5])->assertRedirect('/login');
});

it('lets the reviewer delete their own review, but nobody else\'s', function () {
    $review = Review::factory()->create(['reviewer_id' => $this->reviewer->id, 'seller_id' => $this->seller->id]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->delete("/reviews/{$review->id}")->assertForbidden();
    expect(Review::find($review->id))->not->toBeNull();

    $this->actingAs($this->reviewer)->delete("/reviews/{$review->id}")->assertRedirect();
    expect(Review::find($review->id))->toBeNull();
});

it('throttles review submissions', function () {
    $this->actingAs($this->reviewer);

    foreach (range(1, 10) as $i) {
        $seller = User::factory()->create();
        $this->post("/seller/{$seller->id}/reviews", ['rating' => 5])->assertRedirect();
    }

    $seller = User::factory()->create();
    $this->post("/seller/{$seller->id}/reviews", ['rating' => 5])->assertStatus(429);
});

// -------------------------------------------------------- average rating & visibility

it('computes the average rating and count from visible reviews only', function () {
    Review::factory()->create(['seller_id' => $this->seller->id, 'rating' => 5]);
    Review::factory()->create(['seller_id' => $this->seller->id, 'rating' => 3]);
    Review::factory()->create(['seller_id' => $this->seller->id, 'rating' => 1, 'is_hidden' => true]);

    $this->seller->refresh();
    expect($this->seller->averageRating())->toBe(4.0)
        ->and($this->seller->reviewsCount())->toBe(2);
});

it('returns null average rating when there are no reviews', function () {
    expect($this->seller->averageRating())->toBeNull()
        ->and($this->seller->reviewsCount())->toBe(0);
});

it('shows the average rating and review list on the seller page', function () {
    Review::factory()->create(['seller_id' => $this->seller->id, 'reviewer_id' => $this->reviewer->id, 'rating' => 5, 'comment' => 'ممتاز جداً']);

    $this->get("/seller/{$this->seller->id}")
        ->assertOk()
        ->assertSee('ممتاز جداً')
        ->assertSee($this->reviewer->name);
});

it('never shows a hidden review publicly', function () {
    $hidden = Review::factory()->create(['seller_id' => $this->seller->id, 'comment' => 'تعليق مخفي عن الجميع', 'is_hidden' => true]);

    $this->get("/seller/{$this->seller->id}")->assertDontSee('تعليق مخفي عن الجميع');
});

it('shows the average rating on the listing page too', function () {
    Review::factory()->create(['seller_id' => $this->seller->id, 'rating' => 5]);
    $listing = Listing::factory()->create(['user_id' => $this->seller->id, 'status' => 'active']);

    $this->get($listing->url())->assertOk()->assertSee('5');
});

// ------------------------------------------------------------------- moderation

it('lets a signed-in user prefill and edit their own review from the seller page', function () {
    Review::factory()->create(['reviewer_id' => $this->reviewer->id, 'seller_id' => $this->seller->id, 'rating' => 3, 'comment' => 'تعليقي القديم']);

    $this->actingAs($this->reviewer)
        ->get("/seller/{$this->seller->id}")
        ->assertSee('تعليقي القديم')
        ->assertSee(__('app.reviews.update'));
});
