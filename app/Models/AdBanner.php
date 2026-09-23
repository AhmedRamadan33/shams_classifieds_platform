<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdBannerStatus;
use App\Enums\AdPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AdBanner extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const IMAGE = 'image';

    protected $fillable = [
        'user_id', 'ad_package_id', 'listing_id', 'placement', 'title', 'target_url', 'status', 'rejection_reason',
        'starts_at', 'expires_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'clicks' => 0,
    ];

    protected function casts(): array
    {
        return [
            'placement' => AdPlacement::class,
            'status' => AdBannerStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'clicks' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adPackage(): BelongsTo
    {
        return $this->belongsTo(AdPackage::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForPlacement(Builder $query, AdPlacement $placement): Builder
    {
        return $query->where('placement', $placement->value);
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('status', AdBannerStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $q) => $q->whereNotNull('target_url')->orWhereHas('listing', fn (Builder $l) => $l->visible()));
    }

    public function targetsListing(): bool
    {
        return $this->listing_id !== null;
    }

    public function hasLiveTarget(): bool
    {
        if (! $this->targetsListing()) {
            return $this->target_url !== null;
        }

        $listing = $this->listing;

        return $listing !== null && $listing->isPubliclyListed() && ! $listing->user->is_banned;
    }

    public function destinationLabel(): string
    {
        if ($this->targetsListing()) {
            return $this->listing !== null
                ? __('app.ad_banners.destination_listing', ['title' => $this->listing->title])
                : __('app.ad_banners.listing_removed');
        }

        return $this->target_url ?? __('app.ad_banners.listing_removed');
    }

    public function resolvedUrl(): ?string
    {
        return $this->targetsListing() ? $this->listing?->url() : $this->target_url;
    }

    public function isExpired(): bool
    {
        return $this->status === AdBannerStatus::Expired
            || ($this->status === AdBannerStatus::Active && $this->expires_at !== null && $this->expires_at->isPast());
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
            ->fit(Fit::Max, 1600, 500)
            ->format('webp')
            ->performOnCollections(self::IMAGE)
            ->queued();
    }
}
