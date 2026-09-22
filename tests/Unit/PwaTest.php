<?php

declare(strict_types=1);

/*
 * public/manifest.json and public/sw.js are plain static files: a real webserver (or
 * `php artisan serve`'s dev router, see docs/DEPLOY.md) serves them directly and never routes them
 * through the Laravel kernel, so they cannot be requested through Pest's $this->get() (which only
 * exercises the kernel and would 404 on them, static file serving not being part of it). These
 * checks read them straight off disk instead; resources/views/components/seo.blade.php linking to
 * them is covered by a Feature test.
 */

it('ships a valid, installable web app manifest', function () {
    $path = public_path('manifest.json');
    expect($path)->toBeFile();

    $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('شمس — سوق الإعلانات المبوّبة')
        ->and($manifest['short_name'])->toBe('شمس')
        ->and($manifest['lang'])->toBe('ar')
        ->and($manifest['dir'])->toBe('rtl')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/?source=pwa');

    $sizes = array_column($manifest['icons'], 'sizes');
    expect($sizes)->toContain('192x192')->and($sizes)->toContain('512x512');

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('ships real, correctly sized PNG icons', function () {
    foreach (['icons/icon-192.png' => 192, 'icons/icon-512.png' => 512, 'icons/apple-touch-icon.png' => 180] as $file => $size) {
        $path = public_path($file);
        expect($path)->toBeFile();

        [$width, $height, $type] = getimagesize($path);
        expect($type)->toBe(IMAGETYPE_PNG)->and($width)->toBe($size)->and($height)->toBe($size);
    }
});

it('ships a service worker that never caches authenticated or API routes', function () {
    $path = public_path('sw.js');
    $js = file_get_contents($path);

    expect($js)->toContain("addEventListener('install'")
        ->and($js)->toContain("addEventListener('activate'")
        ->and($js)->toContain("addEventListener('fetch'")
        ->and($js)->toContain('/admin')
        ->and($js)->toContain('/dashboard')
        ->and($js)->toContain("'/api/'")
        ->and($js)->toContain("request.method !== 'GET'");
});
