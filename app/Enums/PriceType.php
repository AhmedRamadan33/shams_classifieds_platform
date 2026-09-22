<?php

declare(strict_types=1);

namespace App\Enums;

enum PriceType: string
{
    case Fixed = 'fixed';
    case Negotiable = 'negotiable';
    case Free = 'free';
    case Contact = 'contact';

    public function label(): string
    {
        return __('app.listing.price_types.'.$this->value);
    }

    /**
     * Whether a numeric price must be entered for this type.
     */
    public function needsPrice(): bool
    {
        return $this === self::Fixed || $this === self::Negotiable;
    }

    /**
     * @return array<string, string> value => Arabic label
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
