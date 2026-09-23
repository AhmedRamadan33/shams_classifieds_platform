<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ExpireAdBanners;
use Illuminate\Console\Command;

class ExpireAdBannersCommand extends Command
{
    protected $signature = 'ad-banners:expire';

    protected $description = 'Mark active ad banners whose expiry date has passed as expired';

    public function handle(ExpireAdBanners $expire): int
    {
        $count = $expire();

        $this->info("Expired {$count} ad banner(s).");

        return self::SUCCESS;
    }
}
