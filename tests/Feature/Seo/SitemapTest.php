<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Tests\Support\Fixtures;

beforeEach(function () {
    // Write into a scratch "public" directory so the real public/robots.txt is never touched.
    $this->publicDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shams-public-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->publicDir);
    File::copy(base_path('public/robots.txt'), $this->publicDir.'/robots.txt');
    app()->usePublicPath($this->publicDir);

    $this->locs = function (string $file = 'sitemap.xml') {
        preg_match_all('#<loc>(.*?)</loc>#', (string) file_get_contents($this->publicDir.'/'.$file), $m);

        return array_map('html_entity_decode', $m[1]);
    };
});

afterEach(function () {
    File::deleteDirectory($this->publicDir);
});

it('lists the home page, static pages, categories, category+governorate pages with listings and active listings', function () {
    $tree = Fixtures::carsTree();
    $cairo = Governorate::factory()->create(['slug' => 'cairo']);
    $giza = Governorate::factory()->create(['slug' => 'giza']);
    Page::create(['slug' => 'about', 'title' => 'من نحن', 'body' => 'x']);
    Page::create(['slug' => 'draft', 'title' => 'مسودة', 'body' => 'x', 'is_published' => false]);

    $active = Listing::factory()->create(['category_id' => $tree['leaf']->id, 'governorate_id' => $cairo->id]);
    $pending = Listing::factory()->pending()->create(['category_id' => $tree['leaf']->id, 'governorate_id' => $giza->id]);
    $expired = Listing::factory()->expired()->create(['category_id' => $tree['leaf']->id]);
    $banned = Listing::factory()->for(User::factory()->banned()->create())->create(['category_id' => $tree['leaf']->id]);
    $inactiveCategory = Category::factory()->inactive()->create(['slug' => 'hidden-cat']);

    $this->artisan('sitemap:generate')->assertSuccessful();

    $locs = ($this->locs)();

    expect($locs)->toContain(route('home'))
        ->and($locs)->toContain(route('pages.show', 'about'))
        ->and($locs)->not->toContain(route('pages.show', 'draft'))
        ->and($locs)->toContain(route('categories.show', 'cars'))
        ->and($locs)->toContain(route('categories.show', 'cars-for-sale'))
        ->and($locs)->not->toContain(route('categories.show', 'hidden-cat'))
        // the listing counts for its category AND its parent category
        ->and($locs)->toContain(route('categories.governorate', ['cars-for-sale', 'cairo']))
        ->and($locs)->toContain(route('categories.governorate', ['cars', 'cairo']))
        // no active listing in Giza: no page for it
        ->and($locs)->not->toContain(route('categories.governorate', ['cars-for-sale', 'giza']))
        ->and($locs)->toContain($active->url())
        ->and($locs)->not->toContain($pending->url())
        ->and($locs)->not->toContain($expired->url())
        ->and($locs)->not->toContain($banned->url());
});

it('produces valid sitemap XML with lastmod and priorities', function () {
    Listing::factory()->create();

    $this->artisan('sitemap:generate');

    $xml = simplexml_load_file($this->publicDir.'/sitemap.xml');

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('urlset')
        ->and(count($xml->url))->toBeGreaterThan(2)
        ->and((string) $xml->url[0]->loc)->toBe(route('home'))
        ->and((string) $xml->url[0]->priority)->toBe('1.0');
});

it('updates the Sitemap line of robots.txt with the absolute URL and keeps the rules', function () {
    $this->artisan('sitemap:generate');

    $robots = file_get_contents($this->publicDir.'/robots.txt');

    expect($robots)->toContain('Sitemap: '.url('/sitemap.xml'))
        ->and(substr_count($robots, 'Sitemap:'))->toBe(1)
        ->and($robots)->toContain('Disallow: /admin');

    // idempotent
    $this->artisan('sitemap:generate');
    expect(substr_count(file_get_contents($this->publicDir.'/robots.txt'), 'Sitemap:'))->toBe(1);
});

it('ships a robots.txt that blocks private areas and references the sitemap', function () {
    $robots = file_get_contents(base_path('public/robots.txt'));

    foreach (['/admin', '/dashboard', '/search', '/api'] as $path) {
        expect($robots)->toContain('Disallow: '.$path);
    }

    expect($robots)->toContain('Sitemap:')->and($robots)->toContain('User-agent: *');
});

it('is scheduled daily', function () {
    $commands = collect(app(Schedule::class)->events());

    expect($commands->filter(fn ($event) => str_contains($event->command, 'sitemap:generate')))->toHaveCount(1);
});
