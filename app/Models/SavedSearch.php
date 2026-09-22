<?php

declare(strict_types=1);

namespace App\Models;

use App\Queries\ListingSearch;
use App\Services\CategoryTree;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A search the user asked to keep, optionally with a daily "new matches" notification
 * (searches:notify, see App\Console\Commands\NotifySavedSearchesCommand).
 *
 * The category/governorate are resolved by slug at read time rather than stored as ids, so a saved
 * search degrades gracefully (site-wide) if its category is deactivated instead of erroring.
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'category_slug', 'governorate_slug', 'filters', 'notify', 'last_notified_at'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'notify' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): ?Category
    {
        return $this->category_slug !== null
            ? CategoryTree::flatten()->firstWhere('slug', $this->category_slug)
            : null;
    }

    public function governorate(): ?Governorate
    {
        return $this->governorate_slug !== null
            ? Governorate::query()->where('slug', $this->governorate_slug)->first()
            : null;
    }

    public function listingSearch(): ListingSearch
    {
        return ListingSearch::make((array) $this->filters, $this->category(), $this->governorate());
    }

    /**
     * The page that shows this search's results, e.g. to link to from its notification.
     */
    public function url(): string
    {
        $category = $this->category();
        $governorate = $this->governorate();
        $query = array_filter((array) $this->filters, fn ($v) => $v !== null && $v !== '');

        if ($category === null) {
            return route('search', $query);
        }

        return $governorate !== null
            ? route('categories.governorate', ['category' => $category->slug, 'governorate' => $governorate->slug, ...$query])
            : route('categories.show', ['category' => $category->slug, ...$query]);
    }
}
