<?php

declare(strict_types=1);

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class SettingsRepository
{
    private const CACHE_KEY = 'app-settings';

    public function all(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value === '' || $value === null) {
                Setting::query()->where('key', $key)->delete();

                continue;
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
        Artisan::call('config:clear');
        $this->applyToConfig();
    }

    public function applyToConfig(): void
    {
        $values = $this->all();

        foreach (SettingsRegistry::configMap() as $key => $configPath) {
            if (array_key_exists($key, $values)) {
                config([$configPath => $values[$key]]);
            }
        }
    }
}
