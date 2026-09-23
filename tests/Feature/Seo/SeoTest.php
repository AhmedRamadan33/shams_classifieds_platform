<?php

declare(strict_types=1);

use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use App\Support\ListingsSeo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

function jsonLdBlocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
}

beforeEach(function () {
    $this->tree = Fixtures::carsTree();
    $this->leaf = $this->tree['leaf'];
    $this->cairo = Governorate::factory()->create(['name' => 'القاهرة', 'slug' => 'cairo']);
});

it('renders title, description, canonical, Open Graph and Twitter tags on every public page', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertSee('<meta name="description" content="', false)
        ->assertSee('<link rel="canonical" href="', false)
        ->assertSee('<meta property="og:title" content="', false)
        ->assertSee('<meta property="og:description" content="', false)
        ->assertSee('<meta property="og:url" content="', false)
        ->assertSee('<meta name="twitter:card" content="', false)
        ->assertSee('<meta name="robots" content="', false);
})->with(['home' => ['/'], 'category' => ['/category/cars'], 'search' => ['/search']]);

it('gives each page its own title', function () {
    $home = $this->get('/')->getContent();
    $category = $this->get('/category/cars-for-sale')->getContent();

    preg_match('#<title>(.*?)</title>#s', $home, $h);
    preg_match('#<title>(.*?)</title>#s', $category, $c);

    expect($h[1])->not->toBe($c[1])->and($c[1])->toContain('سيارات للبيع');
});

it('marks private and auth pages noindex', function () {
    $this->get('/login')->assertSee('<meta name="robots" content="noindex,follow">', false);
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});

it('adds Product + Offer and BreadcrumbList JSON-LD to a listing page', function () {
    $listing = Listing::factory()->create([
        'category_id' => $this->leaf->id,
        'title' => 'تويوتا كورولا 2020',
        'price' => 450000,
        'expires_at' => now()->addDays(20),
        'phone' => '+201099887766',
    ]);

    $html = $this->get($listing->url())->assertOk()->getContent();
    $blocks = collect(jsonLdBlocks($html));

    $product = $blocks->firstWhere('@type', 'Product');
    $breadcrumbs = $blocks->firstWhere('@type', 'BreadcrumbList');

    expect($product)->not->toBeNull()
        ->and($product['name'])->toBe('تويوتا كورولا 2020')
        ->and($product['offers']['@type'])->toBe('Offer')
        ->and($product['offers']['price'])->toBe('450000.00')
        ->and($product['offers']['priceCurrency'])->toBe('EGP')
        ->and($product['offers']['availability'])->toBe('https://schema.org/InStock')
        ->and($product['offers']['url'])->toBe($listing->url())
        ->and($product['category'])->toBe('سيارات للبيع')
        ->and($breadcrumbs['itemListElement'])->toHaveCount(4)
        ->and($breadcrumbs['itemListElement'][0]['name'])->toBe(__('app.nav.home'))
        ->and($breadcrumbs['itemListElement'][3]['position'])->toBe(4);

    expect($html)->not->toContain('99887766');
});

it('omits the price for "call for price" listings and uses 0 for free ones', function () {
    $contact = Listing::factory()->create(['category_id' => $this->leaf->id, 'price' => null, 'price_type' => 'contact']);
    $free = Listing::factory()->create(['category_id' => $this->leaf->id, 'price' => null, 'price_type' => 'free']);

    $offer = fn (Listing $l) => collect(jsonLdBlocks($this->get($l->url())->getContent()))->firstWhere('@type', 'Product')['offers'];

    expect($offer($contact))->not->toHaveKey('price')
        ->and($offer($free)['price'])->toBe('0');
});

it('includes images in the structured data and the og:image tag', function () {
    Storage::fake('public');
    $listing = Listing::factory()->create(['category_id' => $this->leaf->id]);
    $file = Fixtures::image('a.jpg', 800, 600);
    $listing->addMedia($file->getRealPath())->preservingOriginal()->toMediaCollection(Listing::IMAGES);

    $html = $this->get($listing->fresh()->url())->getContent();
    $product = collect(jsonLdBlocks($html))->firstWhere('@type', 'Product');

    expect($product['image'][0])->toStartWith('http')
        ->and($html)->toContain('<meta property="og:image" content="'.$product['image'][0].'">');
});

it('does not emit structured data or index unpublished listings shown to their owner', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->for($owner)->pending()->create(['category_id' => $this->leaf->id]);

    $html = $this->actingAs($owner)->get($listing->url())->assertOk()->getContent();

    expect($html)->not->toContain('application/ld+json')
        ->and($html)->toContain('<meta name="robots" content="noindex,nofollow">');
});

it('escapes hostile characters inside JSON-LD', function () {
    $listing = Listing::factory()->create(['category_id' => $this->leaf->id, 'title' => 'عنوان </script><script>alert(1)</script>']);

    $html = $this->get($listing->url())->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(jsonLdBlocks($html))->not->toBeEmpty();
});

it('indexes an unfiltered category page with a JSON-LD breadcrumb', function () {
    Listing::factory()->create(['category_id' => $this->leaf->id]);

    $html = $this->get('/category/cars-for-sale')->assertOk()->getContent();

    expect($html)->toContain('<meta name="robots" content="index,follow">')
        ->and($html)->toContain('<link rel="canonical" href="'.route('categories.show', 'cars-for-sale').'">')
        ->and(collect(jsonLdBlocks($html))->firstWhere('@type', 'BreadcrumbList')['itemListElement'])->toHaveCount(3);
});

it('sends noindex,follow with a canonical to the base URL for filtered, sorted and searched URLs', function (string $query) {
    Listing::factory()->create(['category_id' => $this->leaf->id]);

    $this->get('/category/cars-for-sale?'.$query)
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('categories.show', 'cars-for-sale').'">', false);
})->with([
    'sort' => ['sort=price_asc'],
    'price filter' => ['price_min=1000'],
    'dynamic filter' => ['f[brand]='.urlencode('تويوتا')],
    'city' => ['city=5'],
]);

it('always noindexes search results and canonicalizes them to /search', function () {
    Listing::factory()->create();

    foreach (['/search', '/search?q='.urlencode('شقة'), '/search?page=2'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('search').'">', false);
    }
});

it('gives page 2+ of an unfiltered listing a self canonical and stays indexable', function () {
    Listing::factory()->count(30)->create(['category_id' => $this->leaf->id]);

    $this->get('/category/cars-for-sale?page=2')
        ->assertOk()
        ->assertSee('<meta name="robots" content="index,follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('categories.show', 'cars-for-sale').'?page=2">', false)
        ->assertSee(__('app.browse.page_n', ['page' => 2]));

    $this->get('/category/cars-for-sale?page=9')->assertSee('<meta name="robots" content="noindex,follow">', false);
});

it('indexes category + governorate pages only when they have an active listing', function () {
    $this->get('/category/cars-for-sale/cairo')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,follow">', false);

    Listing::factory()->create(['category_id' => $this->leaf->id, 'governorate_id' => $this->cairo->id]);

    $this->get('/category/cars-for-sale/cairo')
        ->assertOk()
        ->assertSee('<meta name="robots" content="index,follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('categories.governorate', ['cars-for-sale', 'cairo']).'">', false);

    Governorate::factory()->create(['slug' => 'giza']);
    Listing::factory()->pending()->create(['category_id' => $this->leaf->id, 'governorate_id' => Governorate::where('slug', 'giza')->value('id')]);
    $this->get('/category/cars-for-sale/giza')->assertSee('<meta name="robots" content="noindex,follow">', false);
});

it('exposes the SEO decision as a small pure helper', function () {
    $listings = new LengthAwarePaginator([1], 1, 24, 1);
    $empty = new LengthAwarePaginator([], 0, 24, 1);
    $request = fn (array $query) => Request::create('/x', 'GET', $query);

    expect(ListingsSeo::for($request([]), 'https://x.test/c', $listings)['robots'])->toBe('index,follow')
        ->and(ListingsSeo::for($request(['sort' => 'newest']), 'https://x.test/c', $listings)['robots'])->toBe('noindex,follow')
        ->and(ListingsSeo::for($request(['page' => 1]), 'https://x.test/c', $listings)['robots'])->toBe('index,follow')
        ->and(ListingsSeo::for($request([]), 'https://x.test/c', $empty, requiresListings: true)['robots'])->toBe('noindex,follow')
        ->and(ListingsSeo::for($request([]), 'https://x.test/c', $listings, neverIndex: true)['robots'])->toBe('noindex,follow');
});

it('links the web app manifest and icons on every page (PWA)', function () {
    $this->get('/')
        ->assertSee('<link rel="manifest" href="/manifest.json">', false)
        ->assertSee('<meta name="theme-color" content="#c2410c">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">', false);
});
