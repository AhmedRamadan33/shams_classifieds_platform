<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Page;
use App\Services\CategoryTree;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write public/sitemap.xml (static pages, categories, category+governorate pages with listings, active listings)';

    /** The sitemap protocol allows 50,000 URLs per file; stay well below it. */
    private const MAX_URLS_PER_FILE = 45000;

    public function handle(): int
    {
        /** @var list<Url> $urls */
        $urls = [];

        $urls[] = Url::create(route('home'))->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY);

        foreach (Page::query()->published()->get(['slug', 'updated_at']) as $page) {
            $urls[] = Url::create(route('pages.show', $page->slug))
                ->setLastModificationDate($page->updated_at)
                ->setPriority(0.3)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY);
        }

        foreach (CategoryTree::flatten() as $category) {
            $urls[] = Url::create(route('categories.show', $category->slug))
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY);
        }

        foreach ($this->categoryGovernoratePairs() as [$categorySlug, $governorateSlug]) {
            $urls[] = Url::create(route('categories.governorate', [$categorySlug, $governorateSlug]))
                ->setPriority(0.6)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY);
        }

        Listing::query()
            ->visible()
            ->select(['id', 'slug', 'updated_at'])
            ->chunkById(1000, function ($listings) use (&$urls): void {
                foreach ($listings as $listing) {
                    $urls[] = Url::create($listing->url())
                        ->setLastModificationDate($listing->updated_at)
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY);
                }
            });

        $this->write($urls);
        $this->updateRobotsTxt();

        $this->info(count($urls).' URL(s) written to public/sitemap.xml');

        return self::SUCCESS;
    }

    /**
     * Category + governorate combinations that have at least one active listing. A category page
     * includes its sub-categories, so a listing counts for its category and every ancestor.
     *
     * @return list<array{0: string, 1: string}> [category slug, governorate slug]
     */
    private function categoryGovernoratePairs(): array
    {
        $slugs = Category::query()->pluck('slug', 'id');
        $parents = Category::query()->pluck('parent_id', 'id');
        $governorates = DB::table('governorates')->pluck('slug', 'id');
        $active = CategoryTree::flatten()->pluck('id')->flip();

        $rows = Listing::query()->visible()
            ->select('category_id', 'governorate_id')
            ->distinct()
            ->get();

        $pairs = [];

        foreach ($rows as $row) {
            $categoryId = $row->category_id;
            $guard = 0;

            while ($categoryId !== null && $guard++ < 10) {
                if ($active->has($categoryId) && isset($slugs[$categoryId], $governorates[$row->governorate_id])) {
                    $pairs[$slugs[$categoryId].'|'.$governorates[$row->governorate_id]] = [$slugs[$categoryId], $governorates[$row->governorate_id]];
                }

                $categoryId = $parents[$categoryId] ?? null;
            }
        }

        return array_values($pairs);
    }

    /**
     * One file when it fits; otherwise numbered files plus a sitemap index at sitemap.xml.
     *
     * @param  list<Url>  $urls
     */
    private function write(array $urls): void
    {
        $this->deleteOldChunks();

        if (count($urls) <= self::MAX_URLS_PER_FILE) {
            $sitemap = Sitemap::create();
            array_walk($urls, fn (Url $url) => $sitemap->add($url));
            $sitemap->writeToFile(public_path('sitemap.xml'));

            return;
        }

        $index = SitemapIndex::create();

        foreach (array_chunk($urls, self::MAX_URLS_PER_FILE) as $number => $chunk) {
            $file = 'sitemap-'.($number + 1).'.xml';
            $sitemap = Sitemap::create();
            array_walk($chunk, fn (Url $url) => $sitemap->add($url));
            $sitemap->writeToFile(public_path($file));
            $index->add(url('/'.$file));
        }

        $index->writeToFile(public_path('sitemap.xml'));
    }

    private function deleteOldChunks(): void
    {
        foreach (File::glob(public_path('sitemap-*.xml')) as $file) {
            File::delete($file);
        }
    }

    /**
     * Keep the "Sitemap:" line of robots.txt pointing at this site's absolute sitemap URL.
     */
    private function updateRobotsTxt(): void
    {
        $path = public_path('robots.txt');

        if (! File::exists($path)) {
            return;
        }

        $line = 'Sitemap: '.url('/sitemap.xml');
        $robots = (string) File::get($path);

        $robots = preg_match('/^Sitemap:.*$/mi', $robots)
            ? (string) preg_replace('/^Sitemap:.*$/mi', $line, $robots)
            : rtrim($robots)."\n\n".$line."\n";

        File::put($path, $robots);
    }
}
