<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use App\Models\User;
use App\Queries\ListingSearch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->tree = Fixtures::carsTree();
    $this->parent = $this->tree['parent'];
    $this->leaf = $this->tree['leaf'];
    $this->cairo = Governorate::factory()->create(['name' => 'القاهرة', 'slug' => 'cairo']);
    $this->giza = Governorate::factory()->create(['name' => 'الجيزة', 'slug' => 'giza']);

    $this->field = fn (string $key) => $this->parent->effectiveFields()->firstWhere('key', $key);
    $this->car = function (string $title, array $attrs = [], array $values = []) {
        $listing = Listing::factory()->titled($title)->create([
            'category_id' => $this->leaf->id,
            'governorate_id' => $this->cairo->id,
            ...$attrs,
        ]);

        foreach ($values as $key => $value) {
            ListingFieldValue::create(['listing_id' => $listing->id, 'category_field_id' => ($this->field)($key)->id, 'value' => (string) $value]);
        }

        return $listing;
    };
});

it('lists active listings of a category including all descendant categories', function () {
    $other = Category::factory()->childOf($this->parent)->create(['name' => 'دراجات', 'slug' => 'bikes']);
    ($this->car)('سيارة في القسم الفرعي الأول');
    Listing::factory()->titled('دراجة في القسم الفرعي الثاني')->create(['category_id' => $other->id]);
    Listing::factory()->titled('إعلان من قسم مستقل')->create();

    $this->get('/category/cars')
        ->assertOk()
        ->assertSee('سيارة في القسم الفرعي الأول')
        ->assertSee('دراجة في القسم الفرعي الثاني')
        ->assertDontSee('إعلان من قسم مستقل');

    $this->get('/category/bikes')
        ->assertOk()
        ->assertSee('دراجة في القسم الفرعي الثاني')
        ->assertDontSee('سيارة في القسم الفرعي الأول');
});

it('only shows active listings', function () {
    ($this->car)('إعلان نشط ظاهر');
    ($this->car)('إعلان قيد المراجعة', ['status' => 'pending']);
    ($this->car)('إعلان مرفوض', ['status' => 'rejected']);
    ($this->car)('إعلان منتهي', ['status' => 'expired']);
    ($this->car)('إعلان مباع', ['status' => 'sold']);
    ($this->car)('إعلان نشط لكن تاريخه انتهى', ['expires_at' => now()->subDay()]);
    ($this->car)('إعلان مستخدم محظور', ['user_id' => User::factory()->banned()->create()->id]);

    $response = $this->get('/category/cars-for-sale')->assertOk()->assertSee('إعلان نشط ظاهر');

    foreach (['قيد المراجعة', 'مرفوض', 'منتهي', 'مباع', 'تاريخه انتهى', 'مستخدم محظور'] as $hidden) {
        $response->assertDontSee($hidden);
    }
});

it('returns 404 for unknown or inactive categories', function () {
    $this->get('/category/nope')->assertNotFound();

    $this->leaf->update(['is_active' => false]);
    $this->get('/category/cars-for-sale')->assertNotFound();

    $this->leaf->update(['is_active' => true]);
    $this->parent->update(['is_active' => false]);
    $this->get('/category/cars-for-sale')->assertNotFound();
});

it('shows breadcrumbs, sub-categories and an empty state', function () {
    $this->get('/category/cars')
        ->assertOk()
        ->assertSeeInOrder([__('app.nav.home'), 'سيارات'])
        ->assertSee(route('categories.show', 'cars-for-sale'), false)
        ->assertSee(__('app.browse.empty_title'));
});

it('serves /category/{category}/{governorate} and filters by that governorate', function () {
    ($this->car)('سيارة في القاهرة');
    ($this->car)('سيارة في الجيزة', ['governorate_id' => $this->giza->id]);

    $this->get('/category/cars-for-sale/giza')
        ->assertOk()
        ->assertSee('سيارة في الجيزة')
        ->assertDontSee('سيارة في القاهرة')
        ->assertSee('إعلانات سيارات للبيع في الجيزة');

    $this->get('/category/cars-for-sale/atlantis')->assertNotFound();
});

it('redirects the governorate filter to the canonical path URL and keeps the other filters', function () {
    $this->get('/category/cars-for-sale?governorate=cairo&price_max=500000&page=3')
        ->assertRedirect('/category/cars-for-sale/cairo?price_max=500000');

    $this->get('/category/cars-for-sale?governorate=atlantis')->assertOk();
});

it('filters by city, price range and price type', function () {
    $nasrCity = City::factory()->create(['governorate_id' => $this->cairo->id]);

    ($this->car)('سيارة رخيصة', ['price' => 100000]);
    ($this->car)('سيارة متوسطة في المدينة', ['price' => 300000, 'city_id' => $nasrCity->id]);
    ($this->car)('سيارة غالية', ['price' => 900000]);
    ($this->car)('سيارة مجانية', ['price' => null, 'price_type' => 'free']);

    $names = fn (array $query) => ListingSearch::make($query, $this->leaf)->query()->pluck('title')->all();

    expect($names(['price_min' => 200000, 'price_max' => 500000]))->toBe(['سيارة متوسطة في المدينة'])
        ->and($names(['price_max' => 150000]))->toBe(['سيارة رخيصة'])
        ->and($names(['price_min' => 500000]))->toBe(['سيارة غالية'])
        ->and($names(['city' => $nasrCity->id]))->toBe(['سيارة متوسطة في المدينة'])
        ->and($names(['price_type' => 'free']))->toBe(['سيارة مجانية'])
        ->and($names(['price_min' => '٢٠٠٬٠٠٠', 'price_max' => '٥٠٠٬٠٠٠']))->toBe(['سيارة متوسطة في المدينة'])
        ->and($names(['price_min' => 'abc', 'city' => 'x', 'price_type' => 'weird']))->toHaveCount(4);
});

it('filters by select and boolean dynamic fields', function () {
    ($this->car)('تويوتا مضمونة', [], ['brand' => 'تويوتا', 'year' => 2020, 'warranty' => 1]);
    ($this->car)('كيا بدون ضمان', [], ['brand' => 'كيا', 'year' => 2018, 'warranty' => 0]);
    ($this->car)('هيونداي بلا بيانات');

    $names = fn (array $f) => ListingSearch::make(['f' => $f], $this->leaf)->query()->orderBy('id')->pluck('title')->all();

    expect($names(['brand' => 'تويوتا']))->toBe(['تويوتا مضمونة'])
        ->and($names(['brand' => 'كيا']))->toBe(['كيا بدون ضمان'])
        ->and($names(['warranty' => '1']))->toBe(['تويوتا مضمونة'])
        ->and($names(['warranty' => '0']))->toBe(['كيا بدون ضمان'])
        ->and($names(['brand' => 'تويوتا', 'warranty' => '0']))->toBe([])
        ->and($names(['brand' => '']))->toHaveCount(3);
});

it('filters numeric dynamic fields by range, comparing numbers rather than strings', function () {
    ($this->car)('موديل 999', [], ['year' => 999]);
    ($this->car)('موديل 2005', [], ['year' => 2005]);
    ($this->car)('موديل 2020', [], ['year' => 2020]);
    ($this->car)('موديل بدون سنة');

    $names = fn (array $year) => ListingSearch::make(['f' => ['year' => $year]], $this->leaf)->query()->pluck('title')->all();

    expect($names(['min' => 1000]))->toEqualCanonicalizing(['موديل 2005', 'موديل 2020'])
        ->and($names(['max' => 2005]))->toEqualCanonicalizing(['موديل 999', 'موديل 2005'])
        ->and($names(['min' => 2000, 'max' => 2010]))->toEqualCanonicalizing(['موديل 2005'])
        ->and($names(['min' => '٢٠٢٠']))->toEqualCanonicalizing(['موديل 2020'])
        ->and($names(['min' => 'abc']))->toHaveCount(4)
        ->and($names(['min' => 3000]))->toEqualCanonicalizing([]);
});

it('ignores unknown and non-filterable field keys', function () {
    ($this->car)('سيارة أولى', [], ['model' => 'كورولا']);
    ($this->car)('سيارة ثانية', [], ['model' => 'يارس']);

    $count = fn (array $f) => ListingSearch::make(['f' => $f], $this->leaf)->query()->count();

    expect($count(['model' => 'كورولا']))->toBe(2)
        ->and($count(['hacked' => 'x', 'brand' => ['array']]))->toBe(2);
});

it('applies dynamic filters only when a category is selected', function () {
    ($this->car)('تويوتا', [], ['brand' => 'تويوتا']);
    ($this->car)('كيا', [], ['brand' => 'كيا']);

    expect(ListingSearch::make(['f' => ['brand' => 'تويوتا']])->query()->count())->toBe(2);
});

it('renders the filters for the category and marks the selected values', function () {
    $this->get('/category/cars-for-sale?f[brand]=كيا&price_min=1000')
        ->assertOk()
        ->assertSee('name="f[brand]"', false)
        ->assertSee('name="f[year][min]"', false)
        ->assertSee('name="f[year][max]"', false)
        ->assertSee('name="f[warranty]"', false)
        ->assertDontSee('name="f[model]"', false)
        ->assertSee('value="1000"', false);
});

it('sorts newest first by default and supports price sorting', function () {
    ($this->car)('الأقدم والأرخص', ['price' => 100, 'published_at' => now()->subDays(5)]);
    ($this->car)('الأحدث والأغلى', ['price' => 900, 'published_at' => now()->subDay()]);
    ($this->car)('الوسط', ['price' => 500, 'published_at' => now()->subDays(3)]);
    ($this->car)('مجاني', ['price' => null, 'price_type' => 'free', 'published_at' => now()->subDays(4)]);
    ($this->car)('اتصل للسعر', ['price' => null, 'price_type' => 'contact', 'published_at' => now()->subDays(2)]);

    $order = fn (string $sort) => ListingSearch::make(['sort' => $sort], $this->leaf)->query()->pluck('title')->all();

    expect($order('newest'))->toBe(['الأحدث والأغلى', 'اتصل للسعر', 'الوسط', 'مجاني', 'الأقدم والأرخص'])
        ->and($order('price_asc'))->toBe(['مجاني', 'الأقدم والأرخص', 'الوسط', 'الأحدث والأغلى', 'اتصل للسعر'])
        ->and($order('price_desc'))->toBe(['الأحدث والأغلى', 'الوسط', 'الأقدم والأرخص', 'مجاني', 'اتصل للسعر'])
        ->and($order('unknown'))->toBe($order('newest'));
});

it('always puts featured listings first within the chosen sort', function () {
    ($this->car)('عادي رخيص', ['price' => 100]);
    ($this->car)('مميز غالي', ['price' => 900, 'featured_until' => now()->addDay(), 'published_at' => now()->subDays(9)]);
    ($this->car)('كان مميزاً وانتهى', ['price' => 50, 'featured_until' => now()->subDay()]);

    $order = fn (string $sort) => ListingSearch::make(['sort' => $sort], $this->leaf)->query()->pluck('title')->all();

    expect($order('newest')[0])->toBe('مميز غالي')
        ->and($order('price_asc'))->toBe(['مميز غالي', 'كان مميزاً وانتهى', 'عادي رخيص'])
        ->and($order('price_desc'))->toBe(['مميز غالي', 'عادي رخيص', 'كان مميزاً وانتهى']);
});

it('paginates 24 per page and keeps the query string on the links', function () {
    Listing::factory()->count(30)->create(['category_id' => $this->leaf->id, 'governorate_id' => $this->cairo->id, 'price' => 1000]);

    $page1 = $this->get('/category/cars-for-sale?price_min=1');

    expect(substr_count($page1->getContent(), 'class="group relative'))->toBe(24);
    $page1->assertSee('price_min=1', false)->assertSee('page=2', false);

    $page2 = $this->get('/category/cars-for-sale?price_min=1&page=2');
    expect(substr_count($page2->getContent(), 'class="group relative'))->toBe(6);
});

it('shows the categories, featured and latest listings on the home page', function () {
    Listing::factory()->featured()->titled('إعلان مميز في الرئيسية')->create();
    Listing::factory()->titled('أحدث إعلان في الرئيسية')->create();
    Listing::factory()->pending()->titled('إعلان غير منشور')->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('سيارات')
        ->assertSee(route('categories.show', 'cars'), false)
        ->assertSee('إعلان مميز في الرئيسية')
        ->assertSee('أحدث إعلان في الرئيسية')
        ->assertDontSee('إعلان غير منشور');
});

it('caches the home page sections and rebuilds them when a listing changes', function () {
    $existing = Listing::factory()->titled('إعلان موجود قبل التخزين')->create();
    $this->get('/')->assertSee('إعلان موجود قبل التخزين');
    expect(Cache::has('home.latest'))->toBeTrue();

    DB::enableQueryLog();
    $this->get('/')->assertSee('إعلان موجود قبل التخزين');
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'from `listings`')))->toBeEmpty();
    DB::disableQueryLog();

    Listing::factory()->titled('إعلان جديد بعد التخزين')->create();
    $this->get('/')->assertSee('إعلان جديد بعد التخزين');

    $existing->delete();
    $this->get('/')->assertDontSee('إعلان موجود قبل التخزين');
});

it('flushes the home page cache when listings expire in bulk or their owner is banned', function () {
    $this->freezeTime();

    $listing = Listing::factory()->titled('إعلان سينتهي قريباً')->create(['expires_at' => now()->subMinute()]);
    $ownerListing = Listing::factory()->titled('إعلان صاحبه سيُحظر')->create();

    $live = Listing::factory()->titled('إعلان ساري')->create(['expires_at' => now()->addSecond()]);
    $this->get('/')->assertSee('إعلان ساري')->assertSee('إعلان صاحبه سيُحظر');
    expect(Cache::has('home.latest'))->toBeTrue();

    $ownerListing->user->update(['is_banned' => true]);
    expect(Cache::has('home.latest'))->toBeFalse();
    $this->get('/')->assertDontSee('إعلان صاحبه سيُحظر');

    $this->travel(2)->seconds();
    Artisan::call('listings:expire');
    expect(Cache::has('home.latest'))->toBeFalse();
    $this->get('/')->assertDontSee('إعلان ساري');
});

it('renders the search page with an empty state and without a query', function () {
    $this->get('/search')->assertOk()->assertSee(__('app.browse.all_listings'));
    $this->get('/search?q='.urlencode('لا شيء هنا أبداً'))->assertOk()->assertSee(__('app.browse.empty_title'));
});
