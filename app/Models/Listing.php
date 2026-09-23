<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Listing extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable, SoftDeletes;

    public const IMAGES = 'images';

    public const HOME_CACHE_KEYS = ['home.featured', 'home.latest'];

    protected $fillable = [
        'user_id',
        'category_id',
        'governorate_id',
        'city_id',
        'title',
        'slug',
        'description',
        'search_text',
        'price',
        'price_type',
        'phone',
        'status',
        'rejection_reason',
        'published_at',
        'expires_at',
        'expiry_reminded_at',
        'featured_until',
        'views',
    ];

    protected $attributes = [
        'price_type' => 'fixed',
        'status' => 'pending',
        'search_text' => '',
        'views' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_type' => PriceType::class,
            'status' => ListingStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_reminded_at' => 'datetime',
            'featured_until' => 'datetime',
            'views' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $flush = static fn () => self::flushHomeCache();

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    public static function flushHomeCache(): void
    {
        foreach (self::HOME_CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ListingFieldValue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ListingEvent::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', ListingStatus::Expired->value)
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('status', ListingStatus::Active->value)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()));
        });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->whereNotNull('featured_until')->where('featured_until', '>', now());
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->active()->whereDoesntHave('user', fn (Builder $q) => $q->where('is_banned', true));
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'search_text' => $this->search_text,
        ];
    }

    public function isExpired(): bool
    {
        return $this->status === ListingStatus::Expired
            || ($this->status === ListingStatus::Active && $this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isPubliclyListed(): bool
    {
        return $this->status === ListingStatus::Active && ! $this->isExpired();
    }

    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }

    public function formattedPrice(bool $withType = false): string
    {
        if ($this->price_type === PriceType::Free || $this->price_type === PriceType::Contact) {
            return $this->price_type->label();
        }

        if ($this->price === null) {
            return PriceType::Contact->label();
        }

        $amount = (float) $this->price;
        $formatted = number_format($amount, floor($amount) === $amount ? 0 : 2).' '.config('classifieds.currency_label');

        return $withType && $this->price_type === PriceType::Negotiable
            ? $formatted.' ('.$this->price_type->label().')'
            : $formatted;
    }

    public function whatsappUrl(): string
    {
        $message = __('app.listing_page.whatsapp_message', ['title' => $this->title, 'brand' => __('app.brand')]);

        return 'https://wa.me/'.ltrim($this->phone, '+').'?text='.rawurlencode($message);
    }

    public function url(): string
    {
        return route('listings.show', ['listing' => $this->getKey(), 'slug' => $this->slug]);
    }

    public function coverUrl(string $conversion = 'thumb'): ?string
    {
        $url = $this->getFirstMediaUrl(self::IMAGES, $conversion);

        return $url !== '' ? $url : null;
    }

    public function coverSrcset(): ?string
    {
        $cover = $this->getFirstMedia(self::IMAGES);

        if ($cover === null || ! $cover->hasGeneratedConversion('thumb') || ! $cover->hasGeneratedConversion('medium')) {
            return null;
        }

        return $cover->getUrl('thumb').' 400w, '.$cover->getUrl('medium').' 800w';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGES)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (['thumb' => 400, 'medium' => 800, 'large' => 1600] as $name => $size) {
            $this->addMediaConversion($name)
                ->fit(Fit::Max, $size, $size)
                ->format('webp')
                ->performOnCollections(self::IMAGES)
                ->queued();
        }
    }
}
