<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return __('app.report.statuses.'.$this->value);
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }

    /**
     * @return array<string, string> value => Arabic label
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
