<?php

declare(strict_types=1);

use App\Actions\ApproveListing;
use App\Actions\RejectListing;
use App\Models\AdBanner;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function crawlSite($test, string $host, array $seeds, int $limit, bool $flagLoginRedirects = true, int $perPattern = 2): array
{
    $queue = array_map(fn (string $path) => [$path, 0, 'seed'], $seeds);
    $seen = [];
    $patterns = [];
    $problems = [];
    $crossHost = [];
    $visited = 0;

    while ($queue !== [] && $visited < $limit) {
        [$path, $depth, $from] = array_shift($queue);

        if (isset($seen[$path])) {
            continue;
        }

        $pattern = preg_replace(['#/\d+#', '#page=\d+#'], ['/{id}', 'page=N'], $path);
        $patterns[$pattern] = ($patterns[$pattern] ?? 0) + 1;

        if ($patterns[$pattern] > $perPattern) {
            continue;
        }

        $seen[$path] = true;
        $response = $test->get($host.$path);
        $visited++;
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $problems[] = "HTTP {$status} {$path} (from {$from})";

            continue;
        }

        if ($status >= 300) {
            $location = (string) $response->headers->get('Location');

            if ($flagLoginRedirects && preg_match('#/(admin/)?login$#', (string) parse_url($location, PHP_URL_PATH))) {
                $problems[] = "sent to login: {$path} (from {$from})";
            }

            continue;
        }

        $html = (string) $response->getContent();

        if ($depth >= 2 || ! str_contains($html, '<html')) {
            continue;
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || $href[0] === '#' || preg_match('#^(mailto:|tel:|javascript:)#', $href)) {
                continue;
            }

            if (preg_match('#^https?://#', $href)) {
                $parts = parse_url($href);
                $linkHost = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
                $crawlHost = parse_url($host, PHP_URL_HOST);

                if ($linkHost === $crawlHost) {
                    $href = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
                } else {
                    if (in_array($parts['host'], ['localhost', '127.0.0.1', parse_url((string) config('app.url'), PHP_URL_HOST)], true)) {
                        $crossHost["{$path} -> {$href}"] = true;
                    }

                    continue;
                }
            }

            if ($href[0] !== '/' || preg_match('#^/(logout|admin/logout|banner-click|payments/return|build/|storage/|livewire)#', $href) || preg_match('#\.(css|js|png|jpg|webp|ico|xml|txt)$#', $href)) {
                continue;
            }

            $href = preg_replace('/#.*/', '', $href);

            if (! isset($seen[$href])) {
                $queue[] = [$href, $depth + 1, $path];
            }
        }
    }

    return [$problems, array_keys($crossHost), $visited];
}

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
    DemoSeeder::$listingCount = 24;
    $this->seed(DemoSeeder::class);
});

afterEach(function () {
    DemoSeeder::$listingCount = 200;
});

it('lets every role follow every link on the site without errors, login bounces or foreign-host links', function (string $role) {
    $user = match ($role) {
        'user' => User::where('phone', '+201200000001')->firstOrFail(),
        'moderator' => User::where('phone', '+201111111111')->firstOrFail(),
        'admin' => User::factory()->admin()->create(),
    };

    $this->actingAs($user);

    if ($role === 'user') {
        app(ApproveListing::class)(Listing::factory()->for($user)->pending()->create());
        app(RejectListing::class)(Listing::factory()->for($user)->pending()->create(), 'الصور غير واضحة');
    }

    $seeds = ['/', '/search', '/dashboard', '/favorites', '/notifications', '/messages', '/saved-searches', '/store', '/subscribe', '/advertise', '/banners', '/ads/create', '/profile'];

    if ($role !== 'user') {
        array_push($seeds, '/admin', '/admin/listings', '/admin/ad-banners', '/admin/reports', '/admin/reviews');

        foreach (Listing::withCount('fieldValues')->orderByDesc('field_values_count')->limit(4)->pluck('id') as $id) {
            $seeds[] = "/admin/listings/{$id}";
        }

        foreach (AdBanner::pluck('id') as $id) {
            $seeds[] = "/admin/ad-banners/{$id}";
        }
    }

    if ($role === 'admin') {
        array_push($seeds, '/admin/users', '/admin/categories', '/admin/packages', '/admin/plans', '/admin/payments', '/admin/ad-packages', '/admin/stores', '/admin/subscriptions', '/admin/pages');

        foreach (Payment::limit(3)->pluck('id') as $id) {
            $seeds[] = "/admin/payments/{$id}";
        }
    }

    [$problems, $crossHost, $visited] = crawlSite($this, 'http://crawl-host.test', $seeds, $role === 'user' ? 70 : 90);

    expect($visited)->toBeGreaterThan(20)
        ->and($problems)->toBe([])
        ->and($crossHost)->toBe([]);
})->with(['user', 'moderator', 'admin']);

it('sends a guest to the login page for protected pages and never errors on public ones', function () {
    [$problems, $crossHost, $visited] = crawlSite($this, 'http://crawl-host.test', ['/', '/search', '/p/terms', '/p/about'], 60, false);

    expect($visited)->toBeGreaterThan(15)
        ->and($problems)->toBe([])
        ->and($crossHost)->toBe([]);

    foreach (['/dashboard', '/ads/create', '/advertise', '/banners', '/favorites', '/messages', '/notifications', '/profile', '/saved-searches', '/store', '/subscribe'] as $path) {
        $this->get('http://crawl-host.test'.$path)->assertRedirectContains('/login');
    }

    $this->get('http://crawl-host.test/admin')->assertRedirect();
});
