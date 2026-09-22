<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\ListingSearchText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReindexListingsCommand extends Command
{
    protected $signature = 'listings:reindex';

    protected $description = 'Rebuild the search text of every listing (run it after changing how search text is built)';

    public function handle(): int
    {
        $total = 0;
        $changed = 0;

        Listing::withTrashed()
            ->with('fieldValues.field')
            ->chunkById(200, function ($listings) use (&$total, &$changed): void {
                foreach ($listings as $listing) {
                    $values = $listing->fieldValues
                        ->filter(fn ($row) => $row->field !== null)
                        ->map(fn ($row) => ['field' => $row->field, 'value' => (string) $row->value]);

                    $searchText = ListingSearchText::build($listing->title, $listing->description, $values);
                    $total++;

                    if ($searchText !== $listing->search_text) {
                        // Bypass model events and timestamps: this is maintenance, not an edit.
                        DB::table('listings')->where('id', $listing->id)->update(['search_text' => $searchText]);
                        $changed++;
                    }
                }
            });

        Listing::flushHomeCache();

        $this->info("Reindexed {$total} listing(s), {$changed} changed.");

        return self::SUCCESS;
    }
}
