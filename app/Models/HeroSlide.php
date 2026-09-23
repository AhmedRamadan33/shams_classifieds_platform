<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HeroSlide extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const IMAGE = 'image';

    protected $fillable = ['title', 'subtitle', 'link_url', 'sort_order', 'is_active'];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function imageUrl(string $conversion = 'banner'): ?string
    {
        $url = $this->getFirstMediaUrl(self::IMAGE, $conversion);

        return $url !== '' ? $url : null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('banner')
            ->fit(Fit::Max, 1920, 800)
            ->format('webp')
            ->performOnCollections(self::IMAGE)
            ->queued();
    }
}
