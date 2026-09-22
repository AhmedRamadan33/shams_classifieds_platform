<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\NotifySavedSearches;
use Illuminate\Console\Command;

class NotifySavedSearchesCommand extends Command
{
    protected $signature = 'searches:notify';

    protected $description = 'Notify users of new listings matching their saved searches';

    public function handle(NotifySavedSearches $notify): int
    {
        $count = $notify();

        $this->info("Sent {$count} saved search notification(s).");

        return self::SUCCESS;
    }
}
