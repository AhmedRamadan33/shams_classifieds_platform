<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use Database\Factories\ListingFactory;
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
    /** @use HasFactory<ListingFactory> */
    use HasFactory, InteractsWithMedia, Searchable, SoftDeletes;

    public const IMAGES = 'images';

    /** Cache keys of the home page sections (see HomeController). */
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

    /**
     * Any change to a listing may alter the home page sections (approval, edit, expiry, featuring,
     * deletion), so drop them and let the next request rebuild them. Bulk query-builder updates
     * bypass model events; callers of those (ExpireListings, banning a user) call this directly.
     */
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

    // ------------------------------------------------------------ relations

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

    // --------------------------------------------------------------- scopes

    /**
     * Live listings: status "active" and not past their expiry date.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Listings that ran out: flagged "expired", or still "active" but past expires_at
     * (until the daily expiry command catches up).
     */
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

    /**
     * What visitors may see: active listings whose owner is not banned.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->active()->whereDoesntHave('user', fn (Builder $q) => $q->where('is_banned', true));
    }

    // --------------------------------------------------------------- search

    /**
     * Only used when SCOUT_DRIVER=meilisearch (see ListingSearch::applySearch()); the default MySQL
     * FULLTEXT search never calls this. Deliberately every listing stays indexed regardless of status
     * — Meilisearch only ever supplies a candidate id list, and scopeVisible() (plus every other
     * filter) still runs as a normal SQL WHERE afterwards, so it can never surface a listing SQL
     * would have hidden. That also means a ban or expiry needs no separate re-indexing step.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'search_text' => $this->search_text,
        ];
    }

    // -------------------------------------------------------------- helpers

    public function isExpired(): bool
    {
        return $this->status === ListingStatus::Expired
            || ($this->status === ListingStatus::Active && $this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Live right now (active and not past its expiry). Owner bans are checked by the visible() scope.
     */
    public function isPubliclyListed(): bool
    {
        return $this->status === ListingStatus::Active && ! $this->isExpired();
    }

    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }

    /**
     * "450,000 ج.م", "مجاني" or "اتصل للسعر". With $withType a negotiable price gets its suffix.
     */
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

    /**
     * WhatsApp deep link with an Arabic pre-filled message that mentions the ad title.
     */
    public function whatsappUrl(): string
    {
        $message = __('app.listing_page.whatsapp_message', ['title' => $this->title, 'brand' => __('app.brand')]);

        return 'https://wa.me/'.ltrim($this->phone, '+').'?text='.rawurlencode($message);
    }

    /**
     * Canonical public URL: /ad/{id}/{slug}.
     */
    public function url(): string
    {
        return route('listings.show', ['listing' => $this->getKey(), 'slug' => $this->slug]);
    }

    /**
     * Cover image URL for a conversion (falls back to the original when it is not generated yet).
     */
    public function coverUrl(string $conversion = 'thumb'): ?string
    {
        $url = $this->getFirstMediaUrl(self::IMAGES, $conversion);

        return $url !== '' ? $url : null;
    }

    /**
     * "thumb 400w, medium 800w" for the cover, only once both conversions exist (they are queued).
     */
    public function coverSrcset(): ?string
    {
        $cover = $this->getFirstMedia(self::IMAGES);

        if ($cover === null || ! $cover->hasGeneratedConversion('thumb') || ! $cover->hasGeneratedConversion('medium')) {
            return null;
        }

        return $cover->getUrl('thumb').' 400w, '.$cover->getUrl('medium').' 800w';
    }

    // ---------------------------------------------------------------- media

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGES)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Never upscale: Fit::Max only shrinks. All conversions are WebP and run on the queue.
        foreach (['thumb' => 400, 'medium' => 800, 'large' => 1600] as $name => $size) {
            $this->addMediaConversion($name)
                ->fit(Fit::Max, $size, $size)
                ->format('webp')
                ->performOnCollections(self::IMAGES)
                ->queued();
        }
    }
}
