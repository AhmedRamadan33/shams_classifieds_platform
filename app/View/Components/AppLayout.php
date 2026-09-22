<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  array<int, array<string, mixed>>  $jsonLd  schema.org blocks rendered as JSON-LD
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public string $robots = 'index,follow',
        public ?string $image = null,
        public string $ogType = 'website',
        public array $jsonLd = [],
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
