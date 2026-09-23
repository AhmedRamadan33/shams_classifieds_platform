<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('listings:expire')->daily()->withoutOverlapping();

Schedule::command('ad-banners:expire')->daily()->withoutOverlapping();

Schedule::command('listings:remind-expiring')->daily()->withoutOverlapping();

Schedule::command('searches:notify')->daily()->withoutOverlapping();

Schedule::command('listings:purge')->weekly()->withoutOverlapping();

Schedule::command('sitemap:generate')->daily()->withoutOverlapping();

Schedule::command('backup:clean')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('03:00');
