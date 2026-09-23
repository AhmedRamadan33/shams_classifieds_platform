<?php

declare(strict_types=1);

use App\Support\RelativeUrl;

it('strips the host and keeps the path and query', function (?string $input, string $expected) {
    expect(RelativeUrl::of($input, '/fallback'))->toBe($expected);
})->with([
    ['http://localhost:8000/ad/12/slug', '/ad/12/slug'],
    ['https://127.0.0.1:8000/dashboard?status=rejected', '/dashboard?status=rejected'],
    ['/messages/3', '/messages/3'],
    ['http://example.test', '/fallback'],
    ['', '/fallback'],
    [null, '/fallback'],
]);
