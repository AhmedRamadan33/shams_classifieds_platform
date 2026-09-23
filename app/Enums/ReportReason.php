<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportReason: string
{
    case Scam = 'scam';
    case Duplicate = 'duplicate';
    case Prohibited = 'prohibited';
    case Sold = 'sold';
    case Other = 'other';

    public function label(): string
    {
        return __('app.report.reasons.'.$this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason) => [$reason->value => $reason->label()])
            ->all();
    }
}
