<?php

declare(strict_types=1);

use App\Enums\ListingEventType;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'صاحب الإعلانات']);
    $this->other = User::factory()->create();
});

it('requires a signed in user with a verified phone', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())->get('/dashboard')
        ->assertRedirect(route('phone.verification.notice'));
});

it('shows only the user\'s own listings', function () {
    Listing::factory()->for($this->user)->titled('إعلاني النشط')->create();
    Listing::factory()->for($this->other)->titled('إعلان شخص آخر')->create();

    $this->actingAs($this->user)->get('/dashboard')
        ->assertOk()
        ->assertSee('إعلاني النشط')
        ->assertDontSee('إعلان شخص آخر');
});

it('has tabs per status with counts and filters the list by tab', function () {
    Listing::factory()->for($this->user)->pending()->titled('إعلان قيد المراجعة')->create();
    Listing::factory()->for($this->user)->titled('إعلان نشط ظاهر')->create();
    Listing::factory()->for($this->user)->expired()->titled('إعلان منتهي المدة')->create();
    Listing::factory()->for($this->user)->rejected('الصور غير واضحة')->titled('إعلان مرفوض')->create();
    Listing::factory()->for($this->user)->sold()->titled('إعلان تم بيعه')->create();
    Listing::factory()->for($this->user)->create(['status' => 'active', 'expires_at' => now()->subDay(), 'title' => 'نشط بتاريخ منتهي', 'slug' => 'x']);

    $this->actingAs($this->user);

    $this->get('/dashboard?status=pending')->assertSee('إعلان قيد المراجعة')->assertDontSee('إعلان نشط ظاهر');
    $this->get('/dashboard?status=active')->assertSee('إعلان نشط ظاهر')->assertDontSee('نشط بتاريخ منتهي');
    $this->get('/dashboard?status=expired')->assertSee('إعلان منتهي المدة')->assertSee('نشط بتاريخ منتهي')->assertDontSee('إعلان نشط ظاهر');
    $this->get('/dashboard?status=rejected')->assertSee('إعلان مرفوض');
    $this->get('/dashboard?status=sold')->assertSee('إعلان تم بيعه');

    $response = $this->get('/dashboard');
    $response->assertSeeInOrder([__('app.listing.statuses.pending'), '1', __('app.listing.statuses.active'), '1', __('app.listing.statuses.expired'), '2']);
});

it('opens the first tab that has listings when there are no active ones', function () {
    Listing::factory()->for($this->user)->pending()->titled('الإعلان الوحيد قيد المراجعة')->create();

    $this->actingAs($this->user)->get('/dashboard')->assertSee('الإعلان الوحيد قيد المراجعة');
});

it('shows the rejection reason on rejected listings', function () {
    Listing::factory()->for($this->user)->rejected('الصور غير واضحة ولا تظهر السلعة')->create();

    $this->actingAs($this->user)->get('/dashboard?status=rejected')
        ->assertOk()
        ->assertSee('الصور غير واضحة ولا تظهر السلعة');
});

it('shows views, phone clicks, WhatsApp clicks and favorites per listing', function () {
    $listing = Listing::factory()->for($this->user)->create(['views' => 1234]);
    $listing->events()->createMany([
        ['type' => ListingEventType::PhoneClick], ['type' => ListingEventType::PhoneClick], ['type' => ListingEventType::PhoneClick],
        ['type' => ListingEventType::WhatsappClick],
        ['type' => ListingEventType::View],
    ]);
    Favorite::create(['user_id' => $this->other->id, 'listing_id' => $listing->id]);

    $this->actingAs($this->user)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('app.my_listings.views', ['count' => '1,234']))
        ->assertSee(__('app.my_listings.phone_clicks', ['count' => 3]))
        ->assertSee(__('app.my_listings.whatsapp_clicks', ['count' => 1]))
        ->assertSee(__('app.my_listings.favorites', ['count' => 1]));
});

it('offers actions according to the listing state', function () {
    $active = Listing::factory()->for($this->user)->create(['expires_at' => now()->addDays(20)]);
    $expiring = Listing::factory()->for($this->user)->create(['expires_at' => now()->addDays(3)]);

    $this->actingAs($this->user);

    $html = $this->get('/dashboard')->getContent();

    expect($html)->toContain(route('listings.edit', $active))
        ->and($html)->toContain(route('listings.sold', $active))
        ->and($html)->toContain(route('listings.destroy', $active))
        ->and($html)->not->toContain(route('listings.renew', $active))
        ->and($html)->toContain(route('listings.renew', $expiring));
});

it('offers renewal for expired listings and no "sold" action', function () {
    $expired = Listing::factory()->for($this->user)->expired()->create();

    $html = $this->actingAs($this->user)->get('/dashboard?status=expired')->getContent();

    expect($html)->toContain(route('listings.renew', $expired))
        ->and($html)->not->toContain(route('listings.sold', $expired));
});

it('does not list soft deleted listings', function () {
    Listing::factory()->for($this->user)->titled('إعلان محذوف قديم')->create()->delete();

    $this->actingAs($this->user)->get('/dashboard')->assertDontSee('إعلان محذوف قديم');
});

it('shows an empty state when there is nothing to list', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee(__('app.my_listings.empty_title'));
});

it('paginates the listings', function () {
    Listing::factory()->count(12)->for($this->user)->create();

    $html = $this->actingAs($this->user)->get('/dashboard')->getContent();
    expect(substr_count($html, 'listings.edit') + substr_count($html, '/edit"'))->toBeGreaterThan(0);

    $this->get('/dashboard?page=2')->assertOk();
});
