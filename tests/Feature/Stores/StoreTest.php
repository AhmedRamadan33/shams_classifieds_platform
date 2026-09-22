<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates a store for the signed-in user', function () {
    $this->actingAs($this->user)
        ->post('/store', ['name' => 'متجر أحمد', 'slug' => 'ahmed-store', 'bio' => 'أفضل الأسعار'])
        ->assertRedirect(route('store.edit'));

    $store = Store::sole();
    expect($store->user_id)->toBe($this->user->id)
        ->and($store->name)->toBe('متجر أحمد')
        ->and($store->slug)->toBe('ahmed-store')
        ->and($store->bio)->toBe('أفضل الأسعار');
});

it('updates the existing store instead of creating a second one', function () {
    Store::factory()->for($this->user)->create(['slug' => 'old-slug']);

    $this->actingAs($this->user)->post('/store', ['name' => 'اسم جديد', 'slug' => 'old-slug']);

    expect(Store::count())->toBe(1)
        ->and(Store::sole()->name)->toBe('اسم جديد');
});

it('validates the slug format and uniqueness, but allows keeping your own slug', function (string $slug) {
    $this->actingAs($this->user)->post('/store', ['name' => 'متجر', 'slug' => $slug])->assertSessionHasErrors('slug');
})->with(['UPPER' => ['Ahmed-Store'], 'arabic' => ['متجر-احمد'], 'spaces' => ['ahmed store'], 'underscore' => ['ahmed_store']]);

it('rejects a slug already used by another store', function () {
    Store::factory()->create(['slug' => 'taken']);

    $this->actingAs($this->user)->post('/store', ['name' => 'متجر', 'slug' => 'taken'])->assertSessionHasErrors('slug');
});

it('lets the owner keep their own slug when re-saving', function () {
    $store = Store::factory()->for($this->user)->create(['slug' => 'mine']);

    $this->actingAs($this->user)
        ->post('/store', ['name' => 'اسم محدث', 'slug' => 'mine'])
        ->assertSessionHasNoErrors();

    expect($store->fresh()->name)->toBe('اسم محدث');
});

it('requires authentication to manage a store', function () {
    $this->get('/store')->assertRedirect('/login');
    $this->post('/store', ['name' => 'x', 'slug' => 'x'])->assertRedirect('/login');
});

it('shows the public store page with its listings', function () {
    $store = Store::factory()->create(['name' => 'متجر السيارات', 'slug' => 'cars-store']);
    Listing::factory()->create(['user_id' => $store->user_id, 'status' => 'active']);

    $this->get('/store/cars-store')
        ->assertOk()
        ->assertSee('متجر السيارات');
});

it('404s for a store belonging to a banned owner', function () {
    $store = Store::factory()->create();
    $store->user->update(['is_banned' => true]);

    $this->get("/store/{$store->slug}")->assertNotFound();
});

it('shows the "متجر مميز" badge only while the owner has an active subscription', function () {
    $store = Store::factory()->create(['slug' => 'no-sub']);
    $this->get("/store/{$store->slug}")->assertDontSee(__('app.stores.active_badge'));

    Subscription::factory()->for($store->user)->active()->create();
    $this->get("/store/{$store->slug}")->assertSee(__('app.stores.active_badge'));
});

it('shows the inactive-subscription notice on the owner\'s own store settings page', function () {
    $store = Store::factory()->for($this->user)->create();

    $this->actingAs($this->user)->get('/store')->assertSee(__('app.stores.inactive_notice'));

    Subscription::factory()->for($this->user)->active()->create();
    $this->actingAs($this->user)->get('/store')->assertSee(__('app.stores.active_badge'))->assertDontSee(__('app.stores.inactive_notice'));
});
