<?php

declare(strict_types=1);

use App\Actions\NotifySavedSearches;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\SavedSearchMatched;
use App\Queries\ListingSearch;
use App\Services\ArabicText;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->governorate = Governorate::factory()->create();

    $this->make = fn (string $title, string $description = 'وصف كامل للإعلان مع كل التفاصيل المطلوبة', array $attrs = []) => Listing::factory()
        ->titled($title, $description)
        ->create(['category_id' => $this->category->id, 'governorate_id' => $this->governorate->id, ...$attrs]);

    $this->search = fn (string $q, array $extra = []) => ListingSearch::make(['q' => $q, ...$extra], null, null)
        ->query()->pluck('listings.title')->all();
});

it('finds "شقة" when searching the variant spelling "شقه" and vice versa', function () {
    ($this->make)('شقة للبيع في مدينة نصر');
    ($this->make)('سيارة تويوتا كورولا');

    expect(($this->search)('شقه'))->toBe(['شقة للبيع في مدينة نصر'])
        ->and(($this->search)('شقة'))->toBe(['شقة للبيع في مدينة نصر']);
});

it('ignores hamza variants, diacritics and tatweel in the query', function () {
    ($this->make)('أثاث منزلي كامل للبيع');

    expect(($this->search)('اثاث'))->toHaveCount(1)
        ->and(($this->search)('إثاث'))->toHaveCount(1)
        ->and(($this->search)('أَثَاث'))->toHaveCount(1)
        ->and(($this->search)('أثـــاث'))->toHaveCount(1);
});

it('matches word prefixes', function () {
    ($this->make)('مطلوب مهندس ديكور');

    expect(($this->search)('مهن'))->toHaveCount(1)
        ->and(($this->search)('ديك'))->toHaveCount(1)
        ->and(($this->search)('مطل'))->toHaveCount(1)
        ->and(($this->search)('هندس'))->toHaveCount(0)
        ->and(($this->search)('ندس'))->toHaveCount(0);
});

it('requires every term (AND)', function () {
    ($this->make)('شقة للبيع في المعادي');
    ($this->make)('شقة للايجار في مدينة نصر');

    expect(($this->search)('شقة المعادي'))->toBe(['شقة للبيع في المعادي'])
        ->and(($this->search)('شقة'))->toHaveCount(2)
        ->and(($this->search)('شقة الاسكندرية'))->toHaveCount(0);
});

it('searches the description as well as the title', function () {
    ($this->make)('عنوان عادي جداً', 'يوجد مصعد جراج خاص أمن على مدار الساعة');

    expect(($this->search)('جراج'))->toHaveCount(1)
        ->and(($this->search)('مصعد جراج'))->toHaveCount(1)
        ->and(($this->search)('حمام'))->toHaveCount(0);
});

it('searches the values of the dynamic fields', function () {
    $listing = ($this->make)('سيارة للبيع بحالة جيدة');
    $listing->update(['search_text' => ArabicText::normalize('سيارة للبيع بحالة جيدة هيونداي 2019')]);

    expect(($this->search)('هيونداي'))->toHaveCount(1)
        ->and(($this->search)('2019'))->toHaveCount(1);
});

it('understands Arabic-Indic digits in the query', function () {
    ($this->make)('موبايل ايفون 15 برو جديد');

    expect(($this->search)('١٥'))->toHaveCount(1);
});

it('falls back to LIKE for terms shorter than 3 characters', function () {
    ($this->make)('كاميرا كانون EOS R5 جديدة');
    ($this->make)('كاميرا سوني A7 مستعملة');

    expect(($this->search)('r5'))->toBe(['كاميرا كانون EOS R5 جديدة'])
        ->and(($this->search)('a7'))->toBe(['كاميرا سوني A7 مستعملة'])
        ->and(($this->search)('كاميرا a7'))->toBe(['كاميرا سوني A7 مستعملة'])
        ->and(($this->search)('كاميرا'))->toHaveCount(2);
});

it('escapes LIKE wildcards in short terms', function () {
    ($this->make)('عرض خاص خصم 50% على الكل');
    ($this->make)('عرض خاص بدون خصم');

    expect(($this->search)('a_'))->toHaveCount(0)
        ->and(($this->search)('%'))->toHaveCount(2);
});

it('is not broken by boolean-mode operators and quotes in the query', function (string $q) {
    ($this->make)('شقة للبيع في مدينة نصر');

    expect(fn () => ($this->search)($q))->not->toThrow(Throwable::class);
})->with(['+شقة', '-شقة', '"شقة"', 'شقة*', '(شقة)', '>شقة<', '~شقة', '@شقة', '+++', '"', '())((', 'شقة AND OR NOT']);

it('returns everything when the query has no usable terms', function () {
    ($this->make)('إعلان أول');
    ($this->make)('إعلان ثان');

    expect(($this->search)(''))->toHaveCount(2)
        ->and(($this->search)('   '))->toHaveCount(2)
        ->and(($this->search)('+-*'))->toHaveCount(2);
});

it('only returns visible listings', function () {
    ($this->make)('شقة نشطة للبيع');
    ($this->make)('شقة قيد المراجعة', attrs: ['status' => 'pending']);
    ($this->make)('شقة منتهية', attrs: ['status' => 'expired']);
    ($this->make)('شقة بائعها محظور', attrs: ['user_id' => User::factory()->banned()->create()->id]);

    expect(($this->search)('شقة'))->toBe(['شقة نشطة للبيع']);
});

it('combines the search with filters', function () {
    ($this->make)('شقة رخيصة', attrs: ['price' => 100000]);
    ($this->make)('شقة غالية', attrs: ['price' => 900000]);

    expect(($this->search)('شقة', ['price_max' => 200000]))->toBe(['شقة رخيصة'])
        ->and(($this->search)('شقة', ['price_min' => 500000]))->toBe(['شقة غالية']);
});

it('searches through the public /search page', function () {
    ($this->make)('شقة للبيع في مدينة نصر');
    ($this->make)('سيارة تويوتا');

    $this->get('/search?q='.urlencode('شقه'))
        ->assertOk()
        ->assertSee('شقة للبيع في مدينة نصر')
        ->assertDontSee('سيارة تويوتا');
});

it('filters the search by category and dynamic fields', function () {
    $tree = Fixtures::carsTree();
    $year = $tree['parent']->effectiveFields()->firstWhere('key', 'year');

    $old = Listing::factory()->titled('تويوتا كورولا قديمة')->create(['category_id' => $tree['leaf']->id, 'governorate_id' => $this->governorate->id]);
    $new = Listing::factory()->titled('تويوتا كورولا حديثة')->create(['category_id' => $tree['leaf']->id, 'governorate_id' => $this->governorate->id]);
    ListingFieldValue::create(['listing_id' => $old->id, 'category_field_id' => $year->id, 'value' => '2010']);
    ListingFieldValue::create(['listing_id' => $new->id, 'category_field_id' => $year->id, 'value' => '2022']);
    ($this->make)('تويوتا في قسم آخر');

    $this->get('/search?'.http_build_query(['q' => 'تويوتا', 'category' => 'cars', 'f' => ['year' => ['min' => 2015]]]))
        ->assertOk()
        ->assertSee('تويوتا كورولا حديثة')
        ->assertDontSee('تويوتا كورولا قديمة')
        ->assertDontSee('تويوتا في قسم آخر');
});

it('finds a noun written with the definite article when searched without it, and vice versa', function () {
    ($this->make)('الشقة الفاخرة للبيع');
    ($this->make)('سيارة تويوتا');

    expect(($this->search)('شقة'))->toBe(['الشقة الفاخرة للبيع'])
        ->and(($this->search)('الشقة'))->toBe(['الشقة الفاخرة للبيع'])
        ->and(($this->search)('فاخرة'))->toBe(['الشقة الفاخرة للبيع'])
        ->and(($this->search)('بيع'))->toBe(['الشقة الفاخرة للبيع']);
});

it('sees through glued prepositions such as «بالقاهرة» and «وسيارة»', function () {
    ($this->make)('شقة بالقاهرة الجديدة', 'وسيارة مرسيدس للبيع مع الشقة');
    ($this->make)('محل تجاري');

    expect(($this->search)('قاهرة'))->toBe(['شقة بالقاهرة الجديدة'])
        ->and(($this->search)('القاهرة'))->toBe(['شقة بالقاهرة الجديدة'])
        ->and(($this->search)('سيارة'))->toBe(['شقة بالقاهرة الجديدة']);
});

it('keeps requiring all terms when the article is involved', function () {
    ($this->make)('الشقة في المعادي');
    ($this->make)('الشقة في الشيخ زايد');

    expect(($this->search)('شقة المعادي'))->toBe(['الشقة في المعادي'])
        ->and(($this->search)('الشقة زايد'))->toBe(['الشقة في الشيخ زايد']);
});

it('still finds listings indexed before the variants existed', function () {
    $old = ($this->make)('الشقة القديمة');
    $old->update(['search_text' => ArabicText::normalize('الشقة القديمة')]);

    expect(($this->search)('الشقة'))->toBe(['الشقة القديمة']);
});

it('reindexes existing listings with the new search text', function () {
    $listing = ($this->make)('الشقة القديمة');
    $listing->update(['search_text' => ArabicText::normalize('الشقة القديمة')]);
    expect(($this->search)('شقة'))->toBe([]);

    $this->artisan('listings:reindex')->expectsOutputToContain('1 changed')->assertSuccessful();

    expect(($this->search)('شقة'))->toBe(['الشقة القديمة'])
        ->and($listing->fresh()->updated_at->equalTo($listing->updated_at))->toBeTrue();

    $this->artisan('listings:reindex')->expectsOutputToContain('0 changed')->assertSuccessful();
});

it('finds committed listings through a saved search that filters by "q"', function () {
    $listing = ($this->make)('شقة فاخرة للبيع في القاهرة الجديدة');
    ($this->make)('سيارة تويوتا للبيع');
    $user = User::factory()->create();
    $search = SavedSearch::factory()->for($user)->create(['name' => 'شقق', 'notify' => true, 'filters' => ['q' => 'شقة فاخرة']]);

    expect($search->listingSearch()->query()->pluck('title')->all())->toBe([$listing->title]);

    $this->travel(1)->minute();
    Notification::fake();
    app(NotifySavedSearches::class)();
    Notification::assertNothingSent();

    $this->travel(1)->minute();
    $fresh = ($this->make)('شقة فاخرة أخرى للبيع', attrs: ['published_at' => now()]);
    app(NotifySavedSearches::class)();
    Notification::assertSentTo($user, SavedSearchMatched::class, fn ($n) => $n->count === 1);
});
