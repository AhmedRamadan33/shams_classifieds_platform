<?php

declare(strict_types=1);

it('renders the home page as Arabic right-to-left', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('app.brand'))
        ->assertSee(__('app.home.hero_title'));
});

it('is configured for an Arabic Egyptian market', function () {
    expect(config('app.locale'))->toBe('ar')
        ->and(config('app.fallback_locale'))->toBe('ar')
        ->and(config('app.timezone'))->toBe('Africa/Cairo')
        ->and(config('classifieds.phone_country_code'))->toBe('+20')
        ->and(config('classifieds.currency_label'))->toBe('ج.م')
        ->and(config('queue.default'))->toBe('sync');
});

it('exposes every configuration key required by the plan', function () {
    foreach ([
        'listing_duration_days', 'max_images', 'max_image_kb', 'require_review', 'daily_listing_limit',
        'phone_country_code', 'currency_label', 'blocked_words', 'expiry_reminder_days', 'purge_expired_after_days',
    ] as $key) {
        expect(config('classifieds'))->toHaveKey($key);
    }
});

it('self-hosts the Tajawal font through fontsource', function () {
    $js = file_get_contents(resource_path('js/app.js'));
    $tailwind = file_get_contents(base_path('tailwind.config.js'));

    expect($js)->toContain('@fontsource/tajawal/arabic-400.css')
        ->and($tailwind)->toContain("'Tajawal'");
});

it('uses logical (RTL friendly) Tailwind utilities only', function () {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        if (preg_match('/\b(ml|mr|pl|pr|left|right)-/', file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});
