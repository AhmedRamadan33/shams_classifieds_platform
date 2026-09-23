<?php

declare(strict_types=1);

namespace App\Enums;

enum AdPlacement: string
{
    case HomeTop = 'home_top';
    case SearchSidebar = 'search_sidebar';
    case ListingSidebar = 'listing_sidebar';

    public function label(): string
    {
        return __('app.ad_banners.placements.'.$this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $placement) => [$placement->value => $placement->label()])
            ->all();
    }
}
