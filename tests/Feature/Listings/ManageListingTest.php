<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

beforeEach(function () {
    Storage::fake('public');
    config(['classifieds.require_review' => true, 'classifieds.listing_duration_days' => 30]);

    $this->tree = Fixtures::carsTree();
    $this->leaf = $this->tree['leaf'];
    $this->governorate = Governorate::factory()->create();
    $this->owner = User::factory()->create(['phone' => '+201012345678']);
    $this->other = User::factory()->create();

    // An existing listing created through the real flow, then approved.
    $this->actingAs($this->owner)->post('/ads', Fixtures::listingPayload($this->leaf, $this->governorate, null, [
        'images' => [Fixtures::image('one.jpg', 400, 300), Fixtures::image('two.jpg', 500, 400)],
    ]));
    $this->listing = Listing::with('media')->sole();
    $this->listing->update(['status' => ListingStatus::Active, 'published_at' => now(), 'expires_at' => now()->addDays(20)]);

    $this->update = fn (array $overrides = [], ?User $as = null) => $this->actingAs($as ?? $this->owner)
        ->put("/ads/{$this->listing->id}", Fixtures::listingPayload($this->leaf, $this->governorate, null, $overrides));
});

// ------------------------------------------------------------------- edit

it('shows the edit form only to the owner', function () {
    $response = $this->actingAs($this->owner)->get("/ads/{$this->listing->id}/edit")
        ->assertOk()
        ->assertSee(__('app.listing_form.edit_title'));

    // The form is hydrated with the listing's current values and images.
    $payload = $response->viewData('payload');

    expect($payload['mode'])->toBe('edit')
        ->and($payload['values']['title'])->toBe($this->listing->title)
        ->and($payload['values']['categoryId'])->toBe($this->leaf->id)
        ->and((array) $payload['values']['fields'])->toMatchArray(['brand' => 'تويوتا', 'year' => '2020'])
        ->and($payload['existingImages'])->toHaveCount(2);

    $this->actingAs($this->other)->get("/ads/{$this->listing->id}/edit")->assertForbidden();
});

it('lets staff edit any listing', function () {
    $this->actingAs(User::factory()->moderator()->create())->get("/ads/{$this->listing->id}/edit")->assertOk();
});

it('blocks guests and non-owners from updating', function () {
    auth()->logout();

    $this->put("/ads/{$this->listing->id}", Fixtures::listingPayload($this->leaf, $this->governorate))->assertRedirect(route('login'));
    ($this->update)(['title' => 'محاولة تعديل من مستخدم آخر'], $this->other)->assertForbidden();

    expect($this->listing->fresh()->title)->not->toBe('محاولة تعديل من مستخدم آخر');
});

it('updates the listing, slug, field values and search text', function () {
    ($this->update)([
        'title' => 'هيونداي إلنترا 2022 فبريكا بالكامل',
        'fields' => ['brand' => 'هيونداي', 'model' => 'إلنترا', 'year' => '2022', 'mileage' => null, 'warranty' => '0'],
    ])->assertSessionHasNoErrors();

    $listing = $this->listing->fresh()->load('fieldValues.field');

    expect($listing->title)->toBe('هيونداي إلنترا 2022 فبريكا بالكامل')
        ->and($listing->slug)->toBe('هيونداي-إلنترا-2022-فبريكا-بالكامل')
        ->and($listing->fieldValues->pluck('value', 'field.key')->all())
        ->toBe(['brand' => 'هيونداي', 'model' => 'إلنترا', 'year' => '2022', 'warranty' => '0'])
        ->and($listing->search_text)->toContain('النترا')->not->toContain('الضمان');

    // the "mileage" row was removed because it was emptied
    expect(ListingFieldValue::where('listing_id', $listing->id)->count())->toBe(4);
});

it('sends an edited listing back to pending when moderation is on', function () {
    expect($this->listing->status)->toBe(ListingStatus::Active);

    ($this->update)(['title' => 'عنوان جديد للإعلان بعد التعديل'])
        ->assertSessionHas('success', __('app.listing.updated_pending'));

    expect($this->listing->fresh()->status)->toBe(ListingStatus::Pending);
});

it('sends a rejected listing back to pending and clears the reason', function () {
    $this->listing->update(['status' => ListingStatus::Rejected, 'rejection_reason' => 'صور غير واضحة']);

    ($this->update)()->assertSessionHasNoErrors();

    $listing = $this->listing->fresh();
    expect($listing->status)->toBe(ListingStatus::Pending)->and($listing->rejection_reason)->toBeNull();
});

it('keeps an active listing active on edit when moderation is off, and republishes rejected ones', function () {
    config(['classifieds.require_review' => false]);

    ($this->update)()->assertSessionHasNoErrors();
    expect($this->listing->fresh()->status)->toBe(ListingStatus::Active);

    $this->listing->update(['status' => ListingStatus::Rejected, 'rejection_reason' => 'x']);
    ($this->update)()->assertSessionHasNoErrors();

    $listing = $this->listing->fresh();
    expect($listing->status)->toBe(ListingStatus::Active)
        ->and($listing->rejection_reason)->toBeNull()
        ->and($listing->expires_at->isFuture())->toBeTrue();
});

it('can change the category and drops the values of fields that no longer apply', function () {
    $other = Category::factory()->create(['name' => 'أخرى', 'slug' => 'misc']);

    ($this->update)(['category_id' => $other->id, 'fields' => []])->assertSessionHasNoErrors();

    $listing = $this->listing->fresh();
    expect($listing->category_id)->toBe($other->id)
        ->and($listing->fieldValues()->count())->toBe(0);
});

it('does not require the duplicate check or daily limit when editing', function () {
    config(['classifieds.daily_listing_limit' => 1]);

    // The listing itself already exists (limit reached), yet editing must still work.
    ($this->update)()->assertSessionHasNoErrors();
});

// ----------------------------------------------------------------- images

it('removes selected images and adds new ones', function () {
    [$first, $second] = $this->listing->getMedia(Listing::IMAGES)->all();

    ($this->update)([
        'remove_images' => [$first->id],
        'images' => [Fixtures::image('three.jpg', 600, 500)],
    ])->assertSessionHasNoErrors();

    $media = $this->listing->fresh()->load('media')->getMedia(Listing::IMAGES);

    expect($media)->toHaveCount(2)
        ->and($media->pluck('id')->all())->not->toContain($first->id)
        ->and($media->pluck('id')->all())->toContain($second->id);
});

it('cannot delete media that belongs to another listing', function () {
    $foreign = Listing::factory()->create();
    $file = Fixtures::image('x.jpg'); // keep a reference: the fake temp file is deleted when it is garbage collected
    $foreign->addMedia($file->getRealPath())->preservingOriginal()->toMediaCollection(Listing::IMAGES);
    $foreignMedia = $foreign->getFirstMedia(Listing::IMAGES);

    ($this->update)(['remove_images' => [$foreignMedia->id]])->assertSessionHasNoErrors();

    expect($foreign->fresh()->media()->count())->toBe(1);
});

it('lets the owner pick an existing image as the cover', function () {
    [$first, $second] = $this->listing->getMedia(Listing::IMAGES)->all();

    ($this->update)(['cover' => "existing:{$second->id}"])->assertSessionHasNoErrors();

    expect($this->listing->fresh()->load('media')->getFirstMedia(Listing::IMAGES)->id)->toBe($second->id);
});

it('counts existing images when enforcing the maximum', function () {
    config(['classifieds.max_images' => 3]);

    // 2 existing + 2 new = 4 > 3
    ($this->update)(['images' => [Fixtures::image('a.jpg', 300, 300), Fixtures::image('b.jpg', 300, 300)]])
        ->assertSessionHasErrors('images');

    // removing one first makes it fit
    [$first] = $this->listing->getMedia(Listing::IMAGES)->all();
    ($this->update)([
        'remove_images' => [$first->id],
        'images' => [Fixtures::image('a.jpg', 300, 300), Fixtures::image('b.jpg', 300, 300)],
    ])->assertSessionHasNoErrors();
});

// ----------------------------------------------------------------- delete

it('soft deletes a listing for its owner only', function () {
    $this->actingAs($this->other)->delete("/ads/{$this->listing->id}")->assertForbidden();
    expect(Listing::count())->toBe(1);

    $this->actingAs($this->owner)->delete("/ads/{$this->listing->id}")->assertRedirect(route('dashboard'));

    expect(Listing::count())->toBe(0)
        ->and(Listing::withTrashed()->count())->toBe(1);
});

// ------------------------------------------------------------------ renew

it('renews an expired listing', function () {
    $this->listing->update(['status' => ListingStatus::Expired, 'expires_at' => now()->subDays(3), 'expiry_reminded_at' => now()->subDays(10)]);

    $this->actingAs($this->owner)->post("/ads/{$this->listing->id}/renew")->assertSessionHas('success');

    $listing = $this->listing->fresh();
    expect($listing->status)->toBe(ListingStatus::Active)
        ->and((int) $listing->expires_at->diffInDays(now(), true))->toBeGreaterThanOrEqual(29)
        ->and($listing->expiry_reminded_at)->toBeNull();
});

it('renews an active listing that is within 7 days of expiring', function () {
    $this->listing->update(['expires_at' => now()->addDays(6)]);

    $this->actingAs($this->owner)->post("/ads/{$this->listing->id}/renew")->assertSessionHas('success');

    expect($this->listing->fresh()->expires_at->diffInDays(now(), true))->toBeGreaterThan(28);
});

it('renews an active listing whose date passed before the expiry command ran', function () {
    $this->listing->update(['expires_at' => now()->subHour()]); // still flagged "active"

    $this->actingAs($this->owner)->post("/ads/{$this->listing->id}/renew")->assertSessionHas('success');
});

it('refuses to renew listings that are not near expiry or not eligible', function (ListingStatus $status, int $daysLeft) {
    $this->listing->update(['status' => $status, 'expires_at' => now()->addDays($daysLeft)]);

    $this->actingAs($this->owner)->post("/ads/{$this->listing->id}/renew")->assertSessionHas('error');

    expect($this->listing->fresh()->expires_at->diffInDays(now(), true))->toBeLessThan($daysLeft + 1);
})->with([
    'active with 20 days left' => [ListingStatus::Active, 20],
    'pending' => [ListingStatus::Pending, 3],
    'rejected' => [ListingStatus::Rejected, 3],
    'sold' => [ListingStatus::Sold, 3],
]);

it('only lets the owner renew', function () {
    $this->listing->update(['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);

    $this->actingAs($this->other)->post("/ads/{$this->listing->id}/renew")->assertForbidden();
});

// ------------------------------------------------------------------- sold

it('marks an active listing as sold', function () {
    $this->actingAs($this->owner)->post("/ads/{$this->listing->id}/sold")->assertSessionHas('success');

    expect($this->listing->fresh()->status)->toBe(ListingStatus::Sold);
});

it('cannot mark a non-active listing as sold, nor someone else\'s listing', function () {
    $this->actingAs($this->other)->post("/ads/{$this->listing->id}/sold")->assertForbidden();

    $this->listing->update(['status' => ListingStatus::Pending]);
    $this->actingAs($this->owner)->post("/ads/{$this->listing->id}/sold")->assertSessionHas('error');
});
