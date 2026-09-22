<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Production needs one cron entry: `* * * * * php artisan schedule:run` (see docs/DEPLOY.md).
| withoutOverlapping() keeps a slow run from stacking on top of the next one.
|
*/

// Flag listings whose expires_at has passed.
Schedule::command('listings:expire')->daily()->withoutOverlapping();

// Remind owners a few days before their listing expires.
Schedule::command('listings:remind-expiring')->daily()->withoutOverlapping();

// Notify users of new listings matching their saved searches.
Schedule::command('searches:notify')->daily()->withoutOverlapping();

// Permanently delete listings expired for longer than classifieds.purge_expired_after_days.
Schedule::command('listings:purge')->weekly()->withoutOverlapping();

// Rebuild public/sitemap.xml (and the Sitemap: line of robots.txt).
Schedule::command('sitemap:generate')->daily()->withoutOverlapping();

// Daily backup of the database dump + uploaded images (spatie/laravel-backup), then prune old ones.
Schedule::command('backup:clean')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('03:00');
