<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RemindExpiringListings;
use Illuminate\Console\Command;

class RemindExpiringListingsCommand extends Command
{
    protected $signature = 'listings:remind-expiring';

    protected $description = 'Notify owners whose active listings are about to expire';

    public function handle(RemindExpiringListings $remind): int
    {
        $count = $remind();

        $this->info("Sent {$count} expiry reminder(s).");

        return self::SUCCESS;
    }
}
