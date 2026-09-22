<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\View;

/**
 * Layout for authentication pages: the regular site chrome around a narrow card. Not indexable.
 */
class GuestLayout extends AppLayout
{
    /**
     * @param  array<int, array<string, mixed>>  $jsonLd
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $canonical = null,
        string $robots = 'noindex,follow',
        ?string $image = null,
        string $ogType = 'website',
        array $jsonLd = [],
    ) {
        parent::__construct($title, $description, $canonical, $robots, $image, $ogType, $jsonLd);
    }

    public function render(): View
    {
        return view('layouts.guest');
    }
}
