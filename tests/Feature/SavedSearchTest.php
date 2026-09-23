<?php

declare(strict_types=1);

use App\Actions\NotifySavedSearches;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\SavedSearchMatched;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->tree = Fixtures::carsTree();
    $this->cairo = Governorate::factory()->create(['name' => 'القاهرة', 'slug' => 'cairo']);
    $this->user = User::factory()->create();
});

it('saves the current category search with its filters and category', function () {
    $this->actingAs($this->user)
        ->post('/saved-searches', [
            'name' => 'سيارات تويوتا بالقاهرة',
            'notify' => '1',
            'category_slug' => $this->tree['leaf']->slug,
            'governorate_slug' => $this->cairo->slug,
            'q' => 'تويوتا',
            'price_max' => '300000',
        ])
        ->assertRedirect();

    $search = SavedSearch::sole();
    expect($search->user_id)->toBe($this->user->id)
        ->and($search->name)->toBe('سيارات تويوتا بالقاهرة')
        ->and($search->notify)->toBeTrue()
        ->and($search->category_slug)->toBe($this->tree['leaf']->slug)
        ->and($search->governorate_slug)->toBe($this->cairo->slug)
        ->and($search->filters)->toMatchArray(['q' => 'تويوتا', 'price_max' => '300000']);
});

it('saves a site-wide search with no category', function () {
    $this->actingAs($this->user)
        ->post('/saved-searches', ['name' => 'كل شيء رخيص', 'q' => 'رخيص'])
        ->assertRedirect();

    $search = SavedSearch::sole();
    expect($search->category_slug)->toBeNull()
        ->and($search->governorate_slug)->toBeNull()
        ->and($search->notify)->toBeFalse();
});

it('validates the name and enforces the per-user limit', function () {
    $this->actingAs($this->user)->post('/saved-searches', ['name' => ''])->assertSessionHasErrors('name');

    config(['classifieds.saved_search_limit' => 2]);
    SavedSearch::factory()->count(2)->for($this->user)->create();

    $this->actingAs($this->user)
        ->post('/saved-searches', ['name' => 'بحث زائد عن الحد'])
        ->assertSessionHasErrors('name');

    expect(SavedSearch::count())->toBe(2);
});

it('lists the user\'s saved searches with a live result count, newest first', function () {
    Listing::factory()->create(['category_id' => $this->tree['leaf']->id]);

    $old = SavedSearch::factory()->for($this->user)->create(['name' => 'الأقدم', 'created_at' => now()->subDays(2)]);
    $new = SavedSearch::factory()->for($this->user)->create(['name' => 'الأحدث', 'created_at' => now()->subMinute()]);
    $matching = SavedSearch::factory()->for($this->user)->create(['name' => 'سيارات للبيع', 'category_slug' => $this->tree['leaf']->slug]);

    $response = $this->actingAs($this->user)->get('/saved-searches')->assertOk();
    $response->assertSeeInOrder(['سيارات للبيع', 'الأحدث', 'الأقدم']);
    $response->assertSee(__('app.saved_searches.results_count', ['count' => '1']));
});

it('only shows the user\'s own saved searches', function () {
    SavedSearch::factory()->for(User::factory())->create(['name' => 'بحث شخص آخر']);

    $this->actingAs($this->user)->get('/saved-searches')->assertDontSee('بحث شخص آخر');
});

it('toggles the notify flag and deletes a saved search, but only for its owner', function () {
    $search = SavedSearch::factory()->for($this->user)->create(['notify' => false]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->patch("/saved-searches/{$search->id}", ['notify' => '1'])->assertForbidden();
    $this->actingAs($stranger)->delete("/saved-searches/{$search->id}")->assertForbidden();

    $this->actingAs($this->user)->patch("/saved-searches/{$search->id}", ['notify' => '1'])->assertRedirect();
    expect($search->fresh()->notify)->toBeTrue();

    $this->actingAs($this->user)->delete("/saved-searches/{$search->id}")->assertRedirect();
    expect(SavedSearch::find($search->id))->toBeNull();
});

it('requires authentication to save, list or manage searches', function () {
    $this->post('/saved-searches', ['name' => 'x'])->assertRedirect('/login');
    $this->get('/saved-searches')->assertRedirect('/login');
});

it('notifies the owner only when there are new matches since the search was last checked', function () {
    Notification::fake();
    $search = SavedSearch::factory()->for($this->user)->create([
        'name' => 'سيارات', 'notify' => true, 'category_slug' => $this->tree['leaf']->slug,
    ]);

    app(NotifySavedSearches::class)();
    Notification::assertNothingSent();

    $this->travel(1)->minute();
    Listing::factory()->create(['category_id' => $this->tree['leaf']->id, 'published_at' => now()]);
    app(NotifySavedSearches::class)();

    Notification::assertSentTo($this->user, SavedSearchMatched::class, fn ($n) => $n->count === 1);
    expect($search->fresh()->last_notified_at)->not->toBeNull();
});

it('does not notify again for listings seen in a previous run', function () {
    Notification::fake();
    $search = SavedSearch::factory()->for($this->user)->create(['notify' => true, 'filters' => []]);
    $this->travel(1)->minute();
    Listing::factory()->create(['published_at' => now()]);

    app(NotifySavedSearches::class)();
    Notification::assertSentTo($this->user, SavedSearchMatched::class, fn ($n) => $n->count === 1);

    $this->travel(1)->minute();
    app(NotifySavedSearches::class)();
    Notification::assertSentToTimes($this->user, SavedSearchMatched::class, 1);

    $this->travel(1)->minute();
    Listing::factory()->create(['published_at' => now()]);
    app(NotifySavedSearches::class)();
    Notification::assertSentToTimes($this->user, SavedSearchMatched::class, 2);
});

it('never notifies for a saved search with notify off', function () {
    Notification::fake();
    SavedSearch::factory()->for($this->user)->create(['notify' => false, 'filters' => []]);
    $this->travel(1)->minute();
    Listing::factory()->create(['published_at' => now()]);

    app(NotifySavedSearches::class)();

    Notification::assertNothingSent();
});

it('is scheduled daily', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events())->map(fn ($e) => $e->command);

    expect($events->filter(fn (?string $c) => $c !== null && str_contains($c, 'searches:notify')))->not->toBeEmpty();
});
