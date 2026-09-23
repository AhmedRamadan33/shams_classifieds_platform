<?php

declare(strict_types=1);

namespace App\Support;

final class RelativeUrl
{
    public static function of(?string $url, string $fallback): string
    {
        if ($url === null || $url === '') {
            return $fallback;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['path'])) {
            return $fallback;
        }

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
