<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[ObservedBy(CategoryObserver::class)]
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CategoryField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isLeaf(): bool
    {
        return ! $this->children()->active()->exists();
    }

    public function ancestorsAndSelf(): Collection
    {
        $chain = collect([$this]);
        $current = $this;

        while ($current->parent_id !== null && $chain->count() < 10) {
            $parent = self::query()->find($current->parent_id);

            if ($parent === null) {
                break;
            }

            $chain->prepend($parent);
            $current = $parent;
        }

        return $chain;
    }

    public function descendantIds(bool $includeSelf = false): array
    {
        $ids = $includeSelf ? [$this->id] : [];
        $frontier = [$this->id];

        while ($frontier !== [] && count($ids) < 5000) {
            $frontier = self::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    public function effectiveFields(): Collection
    {
        $chain = $this->ancestorsAndSelf();
        $position = $chain->pluck('id')->flip();

        return CategoryField::query()
            ->whereIn('category_id', $chain->pluck('id'))
            ->get()
            ->sort(fn (CategoryField $a, CategoryField $b) => [$position[$a->category_id], $a->sort_order, $a->id]
                <=> [$position[$b->category_id], $b->sort_order, $b->id])
            ->values();
    }

    public function isPostable(): bool
    {
        return $this->is_active
            && $this->isLeaf()
            && $this->ancestorsAndSelf()->every(fn (Category $category) => $category->is_active);
    }
}
