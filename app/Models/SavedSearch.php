<?php

declare(strict_types=1);

namespace App\Models;

use App\Queries\ListingSearch;
use App\Services\CategoryTree;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
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
