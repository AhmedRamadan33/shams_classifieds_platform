<?php

declare(strict_types=1);

namespace App\Enums;

enum AdBannerStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Active = 'active';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return __('app.ad_banners.statuses.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-900',
            self::Approved => 'bg-sky-100 text-sky-900',
            self::Active => 'bg-green-100 text-green-900',
            self::Rejected => 'bg-red-100 text-red-900',
            self::Expired => 'bg-slate-200 text-slate-800',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Active => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
        };
    }
}
