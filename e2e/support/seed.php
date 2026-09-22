<?php

use App\Models\Listing;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Console\Kernel;

// Seeds a small set of demo data (listings with images, users, a moderator) into the e2e database.
// Run by e2e/global-setup.js with the e2e environment variables.

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DemoSeeder::$listingCount = 36;
(new DemoSeeder)->run();

echo Listing::count().' listings, '.Listing::visible()->count()." visible\n";
