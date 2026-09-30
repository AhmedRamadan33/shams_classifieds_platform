<?php

declare(strict_types=1);

namespace App\Providers;

use App\Settings\SettingsRepository;
use Illuminate\Support\ServiceProvider;
use Throwable;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        try {
            $this->app->make(SettingsRepository::class)->applyToConfig();
        } catch (Throwable) {
        }
    }
}
