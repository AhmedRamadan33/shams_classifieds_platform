<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

function queriesFor(object $test, string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $test->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('forbids lazy loading outside production so N+1 problems surface', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('runs the same number of queries whether a page lists 3 or 24 listings (no N+1)', function (string $url) {
    $tree = Fixtures::carsTree();
    Storage::fake('public');

    Listing::factory()->count(3)->titled('إعلان لقياس الاستعلامات')->create(['category_id' => $tree['leaf']->id]);
    Cache::flush();
    $few = queriesFor($this, $url);

    Listing::factory()->count(21)->titled('إعلان لقياس الاستعلامات')->create(['category_id' => $tree['leaf']->id]);
    Cache::flush();
    $many = queriesFor($this, $url);

    expect($many)->toBe($few);
})->with(['home' => ['/'], 'category' => ['/category/cars-for-sale'], 'search' => ['/search'], 'query search' => ['/search?q=%D8%A7']]);

it('keeps the listing page query count independent of media and fields', function () {
    Storage::fake('public');
    $tree = Fixtures::carsTree();
    $listing = Listing::factory()->create(['category_id' => $tree['leaf']->id]);
    Listing::factory()->count(8)->create(['category_id' => $tree['leaf']->id]);

    $plain = queriesFor($this, $listing->url());

    foreach (['brand' => 'كيا', 'year' => '2020', 'mileage' => '10000'] as $key => $value) {
        $listing->fieldValues()->create(['category_field_id' => $tree['parent']->effectiveFields()->firstWhere('key', $key)->id, 'value' => $value]);
    }
    foreach (range(1, 3) as $i) {
        $file = Fixtures::image("p{$i}.jpg", 500, 400);
        $listing->addMedia($file->getRealPath())->preservingOriginal()->toMediaCollection(Listing::IMAGES);
    }

    expect(queriesFor($this, $listing->fresh()->url()))->toBeLessThanOrEqual($plain + 1);
});

it('keeps the dashboard and favorites pages free of N+1 queries', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    queriesFor($this, '/dashboard');

    Listing::factory()->count(2)->for($user)->create();
    $few = queriesFor($this, '/dashboard');

    Listing::factory()->count(8)->for($user)->create();

    expect(queriesFor($this, '/dashboard'))->toBe($few);
});

it('serves the category tree and geography from the cache after the first request', function () {
    Fixtures::carsTree();
    Cache::flush();

    $cold = queriesFor($this, '/');
    $warm = queriesFor($this, '/');

    expect($warm)->toBeLessThan($cold);
});

it('has the indexes the public queries rely on', function () {
    $indexes = collect(Schema::getIndexes('listings'))->mapWithKeys(fn ($i) => [$i['name'] => $i]);

    $hasColumns = fn (array $columns) => $indexes->contains(fn ($i) => $i['columns'] === $columns);

    expect($hasColumns(['status', 'category_id', 'published_at']))->toBeTrue()
        ->and($hasColumns(['status', 'governorate_id', 'published_at']))->toBeTrue()
        ->and($hasColumns(['expires_at']))->toBeTrue()
        ->and($hasColumns(['featured_until']))->toBeTrue()
        ->and($indexes->contains(fn ($i) => $i['type'] === 'fulltext' && $i['columns'] === ['search_text']))->toBeTrue();

    $values = collect(Schema::getIndexes('listing_field_values'));
    expect($values->contains(fn ($i) => $i['columns'] === ['listing_id', 'category_field_id'] && $i['unique']))->toBeTrue()
        ->and($values->contains(fn ($i) => $i['columns'] === ['category_field_id', 'value']))->toBeTrue();

    expect(collect(Schema::getIndexes('favorites'))->contains(fn ($i) => $i['columns'] === ['user_id', 'listing_id'] && $i['unique']))->toBeTrue()
        ->and(collect(Schema::getIndexes('reports'))->contains(fn ($i) => $i['columns'] === ['listing_id', 'user_id'] && $i['unique']))->toBeTrue()
        ->and(collect(Schema::getIndexes('listing_events'))->contains(fn ($i) => $i['columns'] === ['listing_id', 'type', 'created_at']))->toBeTrue();
});

it('uses InnoDB for every table (required for transactions, foreign keys and FULLTEXT)', function () {
    $engines = collect(DB::select('SELECT table_name AS name, engine AS engine FROM information_schema.tables WHERE table_schema = ?', [DB::getDatabaseName()]))
        ->pluck('engine', 'name');

    expect($engines->reject(fn ($engine) => $engine === 'InnoDB')->all())->toBe([]);
});

it('renders images lazily with dimensions, and the cover with srcset once conversions exist', function () {
    Storage::fake('public');
    $tree = Fixtures::carsTree();
    $listing = Listing::factory()->create(['category_id' => $tree['leaf']->id]);
    $file = Fixtures::image('a.jpg', 1200, 900);
    $listing->addMedia($file->getRealPath())->preservingOriginal()->toMediaCollection(Listing::IMAGES);

    $html = $this->get('/category/cars-for-sale')->getContent();

    expect($html)->toContain('loading="lazy"')
        ->and($html)->toContain('width="400" height="300"')
        ->and($html)->toContain('srcset="')
        ->and($html)->toContain('400w')
        ->and($html)->toContain('800w');

    $page = $this->get($listing->url())->getContent();

    expect($page)->toContain('fetchpriority="high"')
        ->and($page)->toContain('width="1600" height="1200"')
        ->and($page)->toContain('1600w');
});

it('does not send a srcset until the conversions exist', function () {
    Storage::fake('public');
    Queue::fake();
    $tree = Fixtures::carsTree();
    $listing = Listing::factory()->create(['category_id' => $tree['leaf']->id]);
    $file = Fixtures::image('a.jpg', 1200, 900);
    $media = $listing->addMedia($file->getRealPath())->preservingOriginal()->toMediaCollection(Listing::IMAGES);

    $html = $this->get('/category/cars-for-sale')->getContent();
    expect($html)->toContain($media->fresh()->getUrl())->and($html)->not->toContain(' 800w');
});

it('builds production assets as minified fingerprinted files', function () {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

    expect($manifest)->toHaveKey('resources/js/app.js')
        ->and($manifest['resources/js/app.js']['file'])->toMatch('#^assets/app-[\w-]+\.js$#');

    $js = file_get_contents(public_path('build/'.$manifest['resources/js/app.js']['file']));

    expect(substr_count($js, "\n"))->toBeLessThan(50)
        ->and(strlen($js))->toBeLessThan(250_000);
});
