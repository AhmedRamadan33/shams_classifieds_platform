<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ExpireListings;
use Illuminate\Console\Command;

class ExpireListingsCommand extends Command
{
    protected $signature = 'listings:expire';

    protected $description = 'Mark active listings whose expiry date has passed as expired';

    public function handle(ExpireListings $expire): int
    {
        $count = $expire();

        $this->info("Expired {$count} listing(s).");

        return self::SUCCESS;
    }
}
