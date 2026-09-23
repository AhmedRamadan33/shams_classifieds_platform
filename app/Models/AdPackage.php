<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPackage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'placement', 'duration_days', 'price', 'is_active', 'sort_order'];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'placement' => AdPlacement::class,
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function adBanners(): HasMany
    {
        return $this->hasMany(AdBanner::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlacement(Builder $query, AdPlacement $placement): Builder
    {
        return $query->where('placement', $placement->value);
    }

    public function formattedPrice(): string
    {
        $amount = (float) $this->price;

        return number_format($amount, floor($amount) === $amount ? 0 : 2).' '.config('classifieds.currency_label');
    }
}
