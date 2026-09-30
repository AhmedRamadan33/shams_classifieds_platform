<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\SettingsServiceProvider;

return [
    SettingsServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
];
