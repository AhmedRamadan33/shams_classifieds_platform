<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\PurgeExpiredListings;
use Illuminate\Console\Command;

class PurgeListingsCommand extends Command
{
    protected $signature = 'listings:purge';

    protected $description = 'Permanently delete listings that have been expired for a long time (and their images)';

    public function handle(PurgeExpiredListings $purge): int
    {
        $count = $purge();

        $this->info("Purged {$count} listing(s).");

        return self::SUCCESS;
    }
}
