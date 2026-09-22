<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->listing = Listing::factory()->titled('إعلان للمفضلة')->create();
});

it('rejects guests with a 401 for JSON requests so the UI can redirect to login', function () {
    $this->postJson("/ad/{$this->listing->id}/favorite", ['favorite' => true])->assertUnauthorized();
    $this->post("/ad/{$this->listing->id}/favorite")->assertRedirect(route('login'));
});

it('toggles the favorite when no explicit state is sent', function () {
    $this->actingAs($this->user);

    $this->postJson("/ad/{$this->listing->id}/favorite")->assertOk()->assertJson(['favorited' => true]);
    expect(Favorite::count())->toBe(1);

    $this->postJson("/ad/{$this->listing->id}/favorite")->assertOk()->assertJson(['favorited' => false]);
    expect(Favorite::count())->toBe(0);
});

it('is idempotent when an explicit state is sent', function () {
    $this->actingAs($this->user);

    foreach (range(1, 3) as $ignored) {
        $this->postJson("/ad/{$this->listing->id}/favorite", ['favorite' => true])->assertOk()->assertJson(['favorited' => true]);
    }
    expect(Favorite::count())->toBe(1);

    foreach (range(1, 3) as $ignored) {
        $this->postJson("/ad/{$this->listing->id}/favorite", ['favorite' => false])->assertOk()->assertJson(['favorited' => false]);
    }
    expect(Favorite::count())->toBe(0);
});

it('never creates duplicates: the database enforces uniqueness', function () {
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]);

    expect(fn () => Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('keeps favorites separate per user', function () {
    $this->actingAs($this->user)->postJson("/ad/{$this->listing->id}/favorite", ['favorite' => true]);
    $this->actingAs(User::factory()->create())->postJson("/ad/{$this->listing->id}/favorite", ['favorite' => true]);

    expect(Favorite::count())->toBe(2);
});

it('does not allow favoriting listings that are not public, but always allows removing', function () {
    $pending = Listing::factory()->pending()->create();

    $this->actingAs($this->user)->postJson("/ad/{$pending->id}/favorite", ['favorite' => true])->assertNotFound();
    expect(Favorite::count())->toBe(0);

    // A listing that was favorited and later expired can still be removed from the favorites.
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $pending->id]);
    $this->postJson("/ad/{$pending->id}/favorite", ['favorite' => false])->assertOk();
    expect(Favorite::count())->toBe(0);
});

it('lists the user\'s favorites, newest first, hiding listings that are no longer public', function () {
    $first = Listing::factory()->titled('مفضل أول')->create();
    $second = Listing::factory()->titled('مفضل ثاني')->create();
    $hidden = Listing::factory()->titled('مفضل لم يعد ظاهراً')->create();
    Listing::factory()->titled('ليس في المفضلة')->create();

    Favorite::unguarded(fn () => Favorite::create(['user_id' => $this->user->id, 'listing_id' => $first->id, 'created_at' => now()->subDays(2)]));
    Favorite::unguarded(fn () => Favorite::create(['user_id' => $this->user->id, 'listing_id' => $second->id, 'created_at' => now()->subDay()]));
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $hidden->id]);
    $hidden->update(['status' => ListingStatus::Sold]);
    Favorite::create(['user_id' => User::factory()->create()->id, 'listing_id' => $this->listing->id]);

    $this->actingAs($this->user)->get('/favorites')
        ->assertOk()
        ->assertSeeInOrder(['مفضل ثاني', 'مفضل أول'])
        ->assertDontSee('مفضل لم يعد ظاهراً')
        ->assertDontSee('ليس في المفضلة')
        ->assertDontSee('إعلان للمفضلة');
});

it('removes a favorite with the plain form and comes back to the list', function () {
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $this->listing->id]);

    $this->actingAs($this->user)->from('/favorites')
        ->post("/ad/{$this->listing->id}/favorite", ['favorite' => 0])
        ->assertRedirect('/favorites');

    expect(Favorite::count())->toBe(0);
});

it('requires login for the favorites page and shows an empty state', function () {
    $this->get('/favorites')->assertRedirect(route('login'));

    $this->actingAs($this->user)->get('/favorites')->assertOk()->assertSee(__('app.favorites.empty_title'));
});

it('renders the heart as pressed for favorited listings, without one query per card', function () {
    $listings = Listing::factory()->count(5)->create();
    Favorite::create(['user_id' => $this->user->id, 'listing_id' => $listings[0]->id]);

    $this->actingAs($this->user);

    DB::enableQueryLog();
    $html = $this->get('/')->getContent();
    $favoriteQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'from `favorites`'))
        ->count();

    expect($html)->toContain('favorited: true')
        ->and(substr_count($html, 'favorited: false'))->toBeGreaterThanOrEqual(4)
        ->and($favoriteQueries)->toBe(1);
});
