<?php

declare(strict_types=1);

use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create(['name' => 'محمد المعلن']);
    $this->listing = Listing::factory()->for($this->owner)->create([
        'phone' => '+201098765432',
        'title' => 'شقة للبيع في مدينة نصر',
        'description' => "الطابق الثالث\nتشطيب سوبر لوكس",
    ]);
});

// -------------------------------------------------------------- visibility

it('shows an active listing to guests', function () {
    $this->get($this->listing->url())
        ->assertOk()
        ->assertSee('شقة للبيع في مدينة نصر')
        ->assertSee($this->listing->formattedPrice(), false)
        ->assertSee('محمد المعلن')
        ->assertSee('<meta name="robots" content="index,follow">', false);
});

it('renders multi-line descriptions with line breaks but never as raw HTML', function () {
    $this->listing->update(['description' => "سطر أول\n<script>alert(1)</script>\nسطر ثالث"]);

    $this->get($this->listing->fresh()->url())
        ->assertOk()
        ->assertSee('سطر أول<br />', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('returns 404 for listings that are not live', function (ListingStatus $status) {
    $this->listing->update(['status' => $status]);

    $this->get($this->listing->url())->assertNotFound();
})->with([ListingStatus::Pending, ListingStatus::Rejected, ListingStatus::Sold]);

it('returns 410 Gone for expired listings', function () {
    $this->listing->update(['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);

    $this->get($this->listing->url())->assertStatus(410)->assertSee(__('app.errors.410.title'));
});

it('returns 410 for an active listing whose date passed before the expiry command ran', function () {
    $this->listing->update(['expires_at' => now()->subMinute()]);

    $this->get($this->listing->url())->assertStatus(410);
});

it('returns 404 for a soft deleted listing and for a missing id', function () {
    $url = $this->listing->url();
    $this->listing->delete();

    $this->get($url)->assertNotFound();
    $this->get('/ad/99999999/whatever')->assertNotFound();
});

it('hides the listings of a banned user', function () {
    $this->owner->update(['is_banned' => true]);

    $this->get($this->listing->url())->assertNotFound();

    // ... even when it had expired
    $this->listing->update(['status' => ListingStatus::Expired]);
    $this->get($this->listing->url())->assertNotFound();
});

it('does not leak the title of an unpublished listing through the canonical redirect', function () {
    $this->listing->update(['status' => ListingStatus::Pending]);

    $this->get("/ad/{$this->listing->id}")->assertNotFound();
    $this->get("/ad/{$this->listing->id}/wrong")->assertNotFound();
});

it('shows a status banner to the owner instead of a 404', function (ListingStatus $status, string $banner) {
    $this->listing->update(['status' => $status, 'rejection_reason' => 'الصور غير واضحة']);

    $this->actingAs($this->owner)->get($this->listing->url())
        ->assertOk()
        ->assertSee(__("app.listing_page.banner.{$banner}"))
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
})->with([
    'pending' => [ListingStatus::Pending, 'pending'],
    'rejected' => [ListingStatus::Rejected, 'rejected'],
    'sold' => [ListingStatus::Sold, 'sold'],
]);

it('shows the rejection reason to the owner', function () {
    $this->listing->update(['status' => ListingStatus::Rejected, 'rejection_reason' => 'الصور غير واضحة']);

    $this->actingAs($this->owner)->get($this->listing->url())->assertSee('الصور غير واضحة');
});

it('shows an expired listing to its owner and to staff, but not to other users', function () {
    $this->listing->update(['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);

    $this->actingAs($this->owner)->get($this->listing->url())->assertOk()->assertSee(__('app.listing_page.banner.expired'));
    $this->actingAs(User::factory()->moderator()->create())->get($this->listing->url())->assertOk();
    $this->actingAs(User::factory()->create())->get($this->listing->url())->assertStatus(410);
});

it('lets staff open pending listings but not other regular users', function () {
    $this->listing->update(['status' => ListingStatus::Pending]);

    $this->actingAs(User::factory()->admin()->create())->get($this->listing->url())->assertOk();
    $this->actingAs(User::factory()->create())->get($this->listing->url())->assertNotFound();
});

// -------------------------------------------------------- the phone number

it('never includes the phone number in the page HTML', function () {
    $html = $this->get($this->listing->url())->assertOk()->getContent();

    foreach (['+201098765432', '01098765432', '201098765432', '1098765432', '98765432'] as $variant) {
        expect($html)->not->toContain($variant);
    }

    expect($html)->not->toContain('wa.me');
});

it('reveals the phone and a WhatsApp link through the contact endpoint and records the click', function () {
    $response = $this->postJson("/ad/{$this->listing->id}/contact")
        ->assertOk()
        ->assertJsonPath('phone', '+201098765432');

    $whatsapp = $response->json('whatsapp_url');

    expect($whatsapp)->toStartWith('https://wa.me/201098765432?text=')
        ->and(urldecode(explode('text=', $whatsapp)[1]))->toContain('شقة للبيع في مدينة نصر')
        ->and($this->listing->events()->where('type', ListingEventType::PhoneClick->value)->count())->toBe(1);
});

it('records WhatsApp clicks separately', function () {
    $this->postJson("/ad/{$this->listing->id}/contact", ['channel' => 'whatsapp'])->assertOk();

    expect($this->listing->events()->where('type', ListingEventType::WhatsappClick->value)->count())->toBe(1)
        ->and($this->listing->events()->where('type', ListingEventType::PhoneClick->value)->count())->toBe(0);
});

it('does not reveal the phone of listings that are not public', function () {
    $this->listing->update(['status' => ListingStatus::Pending]);

    $this->postJson("/ad/{$this->listing->id}/contact")->assertNotFound();
    expect($this->listing->events()->count())->toBe(0);
});

it('throttles the contact endpoint', function () {
    foreach (range(1, 30) as $ignored) {
        $this->postJson("/ad/{$this->listing->id}/contact")->assertOk();
    }

    $this->postJson("/ad/{$this->listing->id}/contact")->assertStatus(429);
});

// ---------------------------------------------------------------- view counting

it('counts a view once per session and stores a view event', function () {
    $this->get($this->listing->url())->assertOk();
    $this->get($this->listing->url())->assertOk();
    $this->get($this->listing->url())->assertOk();

    expect($this->listing->fresh()->views)->toBe(1)
        ->and($this->listing->events()->where('type', ListingEventType::View->value)->count())->toBe(1);
});

it('counts a view again for a new session', function () {
    $this->get($this->listing->url());
    $this->flushSession();
    $this->get($this->listing->url());

    expect($this->listing->fresh()->views)->toBe(2);
});

it('does not count the owner\'s own visits or previews of unpublished listings', function () {
    $this->actingAs($this->owner)->get($this->listing->url())->assertOk();
    expect($this->listing->fresh()->views)->toBe(0);

    auth()->logout();
    $this->listing->update(['status' => ListingStatus::Pending]);
    $this->actingAs(User::factory()->moderator()->create())->get($this->listing->url())->assertOk();

    expect($this->listing->fresh()->views)->toBe(0);
});

// ------------------------------------------------------------------ details

it('lists the dynamic fields with units, parent fields first', function () {
    $tree = Fixtures::carsTree();
    $listing = Listing::factory()->for($this->owner)->create(['category_id' => $tree['leaf']->id]);

    foreach (['brand' => 'كيا', 'year' => '2021', 'mileage' => '48000', 'warranty' => '1'] as $key => $value) {
        $field = $tree['parent']->effectiveFields()->firstWhere('key', $key);
        $listing->fieldValues()->create(['category_field_id' => $field->id, 'value' => $value]);
    }

    $response = $this->get($listing->url())->assertOk();

    $response->assertSeeInOrder(['الماركة', 'كيا', 'سنة الصنع', '2021', 'الكيلومترات', '48000 كم', 'الضمان', __('app.yes')]);
});

it('shows similar listings from the same category only, excluding itself', function () {
    $category = Category::factory()->create();
    $listing = Listing::factory()->for($this->owner)->create(['category_id' => $category->id, 'title' => 'الإعلان الأصلي هنا']);
    $similar = Listing::factory()->count(9)->create(['category_id' => $category->id]);
    $other = Listing::factory()->titled('إعلان من قسم مختلف تماماً')->create();
    Listing::factory()->pending()->titled('إعلان مشابه غير منشور')->create(['category_id' => $category->id]);

    $response = $this->get($listing->url())->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'class="group relative'))->toBe(8);
    $response->assertDontSee('إعلان من قسم مختلف تماماً')->assertDontSee('إعلان مشابه غير منشور');
});

it('has breadcrumbs through the category ancestors', function () {
    $tree = Fixtures::carsTree();
    $listing = Listing::factory()->create(['category_id' => $tree['leaf']->id]);

    $this->get($listing->url())
        ->assertSeeInOrder([__('app.nav.home'), 'سيارات', 'سيارات للبيع', $listing->title])
        ->assertSee(route('categories.show', 'cars'), false);
});

it('uses the canonical URL and Open Graph tags', function () {
    $this->get($this->listing->url())
        ->assertSee('<link rel="canonical" href="'.$this->listing->url().'">', false)
        ->assertSee('<meta property="og:type" content="product">', false);
});

// ------------------------------------------------------------------ seller

it('lists a seller\'s active listings only', function () {
    Listing::factory()->for($this->owner)->titled('إعلان نشط ثاني للمعلن')->create();
    Listing::factory()->for($this->owner)->pending()->titled('إعلان قيد المراجعة للمعلن')->create();
    Listing::factory()->titled('إعلان لمعلن آخر تماماً')->create();

    $this->get("/seller/{$this->owner->id}")
        ->assertOk()
        ->assertSee('محمد المعلن')
        ->assertSee('شقة للبيع في مدينة نصر')
        ->assertSee('إعلان نشط ثاني للمعلن')
        ->assertDontSee('إعلان قيد المراجعة للمعلن')
        ->assertDontSee('إعلان لمعلن آخر تماماً');
});

it('returns 404 for a banned seller or an unknown seller', function () {
    $this->owner->update(['is_banned' => true]);

    $this->get("/seller/{$this->owner->id}")->assertNotFound();
    $this->get('/seller/999999')->assertNotFound();
});
