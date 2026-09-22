<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Governorate;
use App\Models\Listing;
use App\Queries\ListingSearch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/*
 * These tests only run against a real, running Meilisearch server (SCOUT_DRIVER=meilisearch +
 * MEILISEARCH_HOST reachable). The default setup (SCOUT_DRIVER unset) stays on MySQL FULLTEXT,
 * already covered end to end by SearchTest.php in this directory — that is the path every other
 * test in the suite, and CI, runs against. These tests document and verify the optional Meilisearch
 * path; they skip themselves (never fail) when no server is configured, so a normal `php artisan
 * test` run — with nothing Meilisearch-related in .env — is completely unaffected by them.
 *
 * To run them for real locally: start a Meilisearch server, then
 *   SCOUT_DRIVER=meilisearch MEILISEARCH_HOST=http://127.0.0.1:7700 MEILISEARCH_KEY=... \
 *     php artisan test --filter=MeilisearchSearchTest
 */

beforeEach(function () {
    if (config('scout.driver') !== 'meilisearch') {
        $this->markTestSkipped('SCOUT_DRIVER=meilisearch is not set; the default MySQL FULLTEXT search is covered by SearchTest.php.');
    }

    $host = rtrim((string) config('scout.meilisearch.host'), '/');

    try {
        Http::timeout(2)->get($host.'/health')->throw();
    } catch (Throwable) {
        $this->markTestSkipped("No Meilisearch server reachable at {$host}.");
    }

    try {
        // Fails with "Index not found" the very first time this is ever run against a fresh
        // server (nothing to flush yet); that is not an error worth failing the test over.
        Artisan::call('scout:flush', ['model' => Listing::class]);
    } catch (Throwable) {
        // no-op
    }

    $this->category = Category::factory()->create();
    $this->governorate = Governorate::factory()->create();

    $this->make = fn (string $title, array $attrs = []) => Listing::factory()
        ->titled($title, 'وصف كامل للإعلان مع كل التفاصيل المطلوبة')
        ->create(['category_id' => $this->category->id, 'governorate_id' => $this->governorate->id, ...$attrs]);

    $this->search = fn (string $q, array $extra = []) => ListingSearch::make(['q' => $q, ...$extra], null, null)
        ->query()->pluck('listings.title')->all();
});

/**
 * Meilisearch indexes asynchronously (even a "synchronous" Scout call just enqueues a task on the
 * server), so a freshly created listing is not always searchable the instant create() returns.
 */
function waitForMeilisearchIndexing(Listing $listing, string $term, int $tries = 30): void
{
    for ($i = 0; $i < $tries; $i++) {
        if (Listing::search($term)->keys()->contains($listing->id)) {
            return;
        }

        usleep(100_000);
    }
}

it('finds a listing through the Meilisearch index instead of MySQL FULLTEXT', function () {
    $listing = ($this->make)('شقة فاخرة للبيع في التجمع الخامس');
    waitForMeilisearchIndexing($listing, 'شقة');

    expect(($this->search)('شقة'))->toBe(['شقة فاخرة للبيع في التجمع الخامس']);
});

it('is typo-tolerant, unlike the FULLTEXT boolean-mode fallback', function () {
    $listing = ($this->make)('سيارة تويوتا كورولا موديل حديث');
    waitForMeilisearchIndexing($listing, 'تويوتا');

    // one swapped letter (تويوتا → توبوتا) — MySQL boolean-mode prefix matching would not find this.
    expect(($this->search)('توبوتا'))->toBe(['سيارة تويوتا كورولا موديل حديث']);
});

it('still applies every normal SQL filter on top of the Meilisearch candidates', function () {
    $visible = ($this->make)('أثاث غرفة نوم كامل');
    $banned = ($this->make)('أثاث مكتب فاخر');
    $banned->user->update(['is_banned' => true]);

    waitForMeilisearchIndexing($visible, 'أثاث');
    // Confirmed indexed on the Meilisearch side too, so the exclusion below is proven to come from
    // the SQL "visible" filter (scopeVisible), not from Meilisearch simply not having it yet.
    waitForMeilisearchIndexing($banned, 'أثاث');

    expect(($this->search)('أثاث'))->toBe(['أثاث غرفة نوم كامل']);
});

it('still respects the category filter alongside a Meilisearch text match', function () {
    $other = Category::factory()->create();

    $inCategory = ($this->make)('أثاث غرفة نوم كامل');
    $elsewhere = ($this->make)('أثاث مكتب فاخر', ['category_id' => $other->id]);

    waitForMeilisearchIndexing($inCategory, 'أثاث');
    waitForMeilisearchIndexing($elsewhere, 'أثاث');

    $result = ListingSearch::make(['q' => 'أثاث'], $this->category, null)
        ->query()->pluck('listings.title')->all();

    expect($result)->toBe(['أثاث غرفة نوم كامل']);
});
