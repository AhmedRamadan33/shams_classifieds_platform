<?php

declare(strict_types=1);

namespace App\Enums;

enum ListingStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Sold = 'sold';

    public function label(): string
    {
        return __('app.listing.statuses.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-900',
            self::Active => 'bg-green-100 text-green-900',
            self::Rejected => 'bg-red-100 text-red-900',
            self::Expired => 'bg-slate-200 text-slate-800',
            self::Sold => 'bg-sky-100 text-sky-900',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
            self::Sold => 'info',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
