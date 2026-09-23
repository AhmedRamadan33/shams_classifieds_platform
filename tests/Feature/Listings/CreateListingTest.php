<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

beforeEach(function () {
    Storage::fake('public');
    config(['classifieds.require_review' => true]);

    $this->tree = Fixtures::carsTree();
    $this->leaf = $this->tree['leaf'];
    $this->governorate = Governorate::factory()->create();
    $this->city = City::factory()->create(['governorate_id' => $this->governorate->id]);
    $this->user = User::factory()->create(['phone' => '+201012345678']);

    $this->payload = fn (array $overrides = []) => Fixtures::listingPayload($this->leaf, $this->governorate, $this->city, $overrides);
    $this->submit = fn (array $overrides = []) => $this->actingAs($this->user)->post('/ads', ($this->payload)($overrides));
});

it('requires a signed in user with a verified phone', function () {
    $this->get('/ads/create')->assertRedirect(route('login'));
    $this->post('/ads', ($this->payload)())->assertRedirect(route('login'));

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->get('/ads/create')->assertRedirect(route('phone.verification.notice'));
});

it('renders the multi-step form with the category tree and Arabic strings', function () {
    $this->actingAs($this->user)->get('/ads/create')
        ->assertOk()
        ->assertSee(__('app.listing_form.create_title'))
        ->assertSee('سيارات')
        ->assertSee('listingForm', false);
});

it('creates a pending listing with field values, slug and normalized search text', function () {
    ($this->submit)()->assertRedirect(route('dashboard'))->assertSessionHas('success');

    $listing = Listing::with('fieldValues.field')->sole();

    expect($listing->status)->toBe(ListingStatus::Pending)
        ->and($listing->user_id)->toBe($this->user->id)
        ->and($listing->published_at)->toBeNull()
        ->and($listing->expires_at)->toBeNull()
        ->and($listing->slug)->toBe('تويوتا-كورولا-2020-بحالة-ممتازة')
        ->and($listing->phone)->toBe('+201012345678')
        ->and((string) $listing->price)->toBe('450000.00')
        ->and($listing->fieldValues->pluck('value', 'field.key')->all())->toBe([
            'brand' => 'تويوتا', 'model' => 'كورولا', 'year' => '2020', 'mileage' => '55000', 'warranty' => '1',
        ]);

    expect($listing->search_text)->toContain('بحاله')
        ->and($listing->search_text)->toContain('ممتازه')
        ->and($listing->search_text)->toContain('تويوتا')
        ->and($listing->search_text)->toContain('كورولا')
        ->and($listing->search_text)->toContain('55000')
        ->and($listing->search_text)->toContain('الضمان')
        ->and($listing->search_text)->not->toContain('ة');
});

it('publishes immediately when moderation is switched off', function () {
    config(['classifieds.require_review' => false, 'classifieds.listing_duration_days' => 30]);

    ($this->submit)()->assertSessionHas('success', __('app.listing.created_active'));

    $listing = Listing::sole();

    expect($listing->status)->toBe(ListingStatus::Active)
        ->and($listing->published_at)->not->toBeNull()
        ->and((int) $listing->expires_at->diffInDays(now(), true))->toBeGreaterThanOrEqual(29);
});

it('normalizes Arabic digits in the phone, price and numeric fields', function () {
    ($this->submit)([
        'phone' => '٠١٠١٢٣٤٥٦٧٨',
        'price' => '١٬٢٥٠٫٥',
        'fields' => ['year' => '٢٠٢١', 'mileage' => '٥٠٬٠٠٠'],
    ])->assertSessionHasNoErrors();

    $listing = Listing::with('fieldValues.field')->sole();

    expect($listing->phone)->toBe('+201012345678')
        ->and((string) $listing->price)->toBe('1250.50')
        ->and($listing->fieldValues->pluck('value', 'field.key')->all())
        ->toMatchArray(['year' => '2021', 'mileage' => '50000']);
});

it('validates the base fields', function (array $overrides, string $errorKey) {
    ($this->submit)($overrides)->assertSessionHasErrors($errorKey);
    expect(Listing::count())->toBe(0);
})->with([
    'title too short' => [['title' => 'قصير'], 'title'],
    'title too long' => [['title' => str_repeat('ع', 151)], 'title'],
    'description too short' => [['description' => 'وصف قصير'], 'description'],
    'description too long' => [['description' => str_repeat('ع', 5001)], 'description'],
    'unknown governorate' => [['governorate_id' => 999999], 'governorate_id'],
    'bad price type' => [['price_type' => 'weird'], 'price_type'],
    'negative price' => [['price' => '-5'], 'price'],
    'non numeric price' => [['price' => 'abc'], 'price'],
    'invalid phone' => [['phone' => '123'], 'phone'],
    'missing category' => [['category_id' => null], 'category_id'],
]);

it('requires a price for fixed and negotiable listings but not for free or contact', function () {
    ($this->submit)(['price' => null])->assertSessionHasErrors('price');
    ($this->submit)(['price_type' => 'negotiable', 'price' => null])->assertSessionHasErrors('price');

    ($this->submit)(['price_type' => 'free', 'price' => '999', 'title' => 'سيارة للتبرع مجاناً'])->assertSessionHasNoErrors();
    ($this->submit)(['price_type' => 'contact', 'price' => '999', 'title' => 'سيارة اتصل بنا للسعر'])->assertSessionHasNoErrors();

    expect(Listing::where('price_type', 'free')->sole()->price)->toBeNull()
        ->and(Listing::where('price_type', 'contact')->sole()->price)->toBeNull();
});

it('only accepts an active leaf category', function () {
    ($this->submit)(['category_id' => $this->tree['parent']->id])->assertSessionHasErrors('category_id');

    $this->leaf->update(['is_active' => false]);
    ($this->submit)()->assertSessionHasErrors('category_id');

    $this->leaf->update(['is_active' => true]);
    $this->tree['parent']->update(['is_active' => false]);
    ($this->submit)()->assertSessionHasErrors('category_id');
});

it('checks that the city belongs to the chosen governorate', function () {
    $otherCity = City::factory()->create();

    ($this->submit)(['city_id' => $otherCity->id])->assertSessionHasErrors('city_id');
    ($this->submit)(['city_id' => null])->assertSessionHasNoErrors();
});

it('generates dynamic rules from the category fields', function () {
    ($this->submit)(['fields' => ['brand' => '']])->assertSessionHasErrors('fields.brand');

    ($this->submit)(['fields' => ['brand' => 'ماركة غير موجودة']])->assertSessionHasErrors('fields.brand');

    ($this->submit)(['fields' => ['year' => '']])->assertSessionHasErrors('fields.year');
    ($this->submit)(['fields' => ['year' => 'abcd']])->assertSessionHasErrors('fields.year');

    ($this->submit)(['fields' => ['warranty' => 'maybe']])->assertSessionHasErrors('fields.warranty');

    ($this->submit)(['fields' => ['brand' => 'كيا', 'year' => '2019', 'model' => null, 'mileage' => null, 'warranty' => null]])
        ->assertSessionHasNoErrors();

    expect(Listing::count())->toBe(1);
});

it('uses Arabic field names in error messages', function () {
    ($this->submit)(['fields' => ['year' => '']])->assertSessionHasErrors('fields.year');

    expect(session('errors')->first('fields.year'))->toContain('سنة الصنع');
});

it('ignores field keys that do not belong to the category', function () {
    ($this->submit)(['fields' => ['hacked' => 'x']])->assertSessionHasNoErrors();

    expect(Listing::with('fieldValues.field')->sole()->fieldValues->pluck('field.key')->all())->not->toContain('hacked');
});

it('rejects blocked words', function () {
    config(['classifieds.blocked_words' => ['مخدرات', 'سلاح']]);

    ($this->submit)(['description' => 'سيارة للبيع مع سِلاح مرخص للحماية الشخصية'])->assertSessionHasErrors('description');
    expect(Listing::count())->toBe(0);
});

it('detects the same title in the same category within 24 hours', function () {
    ($this->submit)()->assertSessionHasNoErrors();
    ($this->submit)()->assertSessionHasErrors(['title' => __('app.listing.duplicate')]);

    expect(Listing::count())->toBe(1);

    $this->travel(25)->hours();
    ($this->submit)()->assertSessionHasNoErrors();

    expect(Listing::count())->toBe(2);
});

it('allows the same title for a different user', function () {
    ($this->submit)();

    $this->actingAs(User::factory()->create())->post('/ads', ($this->payload)())->assertSessionHasNoErrors();

    expect(Listing::count())->toBe(2);
});

it('enforces the daily listing limit, counting deleted listings too', function () {
    config(['classifieds.daily_listing_limit' => 2]);

    ($this->submit)(['title' => 'الإعلان الأول للتجربة'])->assertSessionHasNoErrors();
    ($this->submit)(['title' => 'الإعلان الثاني للتجربة'])->assertSessionHasNoErrors();

    Listing::first()->delete();

    ($this->submit)(['title' => 'الإعلان الثالث للتجربة'])
        ->assertSessionHasErrors(['limit' => __('app.listing.daily_limit', ['limit' => 2])]);

    expect(Listing::count())->toBe(1);

    $this->travel(25)->hours();
    ($this->submit)(['title' => 'الإعلان الرابع للتجربة'])->assertSessionHasNoErrors();
});

it('does not let a banned user create listings', function () {
    $this->user->update(['is_banned' => true]);

    ($this->submit)()->assertRedirect(route('login'));
    expect(Listing::count())->toBe(0);
});

it('redirects the public URL to the canonical slug with a 301', function () {
    ($this->submit)();
    $listing = Listing::sole();
    $listing->update(['status' => ListingStatus::Active, 'published_at' => now(), 'expires_at' => now()->addDays(30)]);

    $this->get("/ad/{$listing->id}")->assertStatus(301)->assertRedirect($listing->url());
    $this->get("/ad/{$listing->id}/wrong-slug")->assertStatus(301)->assertRedirect($listing->url());
    $this->get($listing->url())->assertOk()->assertSee($listing->title);

    expect($listing->url())->toBe(url("/ad/{$listing->id}/".rawurlencode($listing->slug)));
});
