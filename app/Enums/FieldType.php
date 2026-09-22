<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Boolean = 'boolean';

    public function label(): string
    {
        return __('app.field_types.'.$this->value);
    }

    /**
     * @return array<string, string> value => Arabic label, for select inputs
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
