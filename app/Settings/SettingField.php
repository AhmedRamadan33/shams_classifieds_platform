<?php

declare(strict_types=1);

namespace App\Settings;

final class SettingField
{
    public function __construct(
        public readonly string $key,
        public readonly string $configPath,
        public readonly string $type = 'text',
        public readonly bool $secret = false,
        public readonly array $options = [],
        public readonly ?string $visibleWhenKey = null,
        public readonly ?string $visibleWhenValue = null,
    ) {}

    public function label(): string
    {
        return __('app.admin.settings.fields.'.strtolower($this->key));
    }

    public function selectOptions(): array
    {
        return collect($this->options)
            ->mapWithKeys(fn (string $value) => [$value => __('app.admin.settings.options.'.strtolower($this->key).'.'.$value)])
            ->all();
    }
}
