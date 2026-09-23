<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_USER = 'user';

    protected $fillable = [
        'name',
        'phone',
        'phone_verified_at',
        'email',
        'password',
        'is_banned',
        'avatar',
        'notify_email',
        'notify_whatsapp',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_banned' => 'boolean',
            'notify_email' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if ($user->roles()->doesntExist()) {
                $user->assignRole(Role::findOrCreate(self::ROLE_USER, 'web'));
            }
        });

        static::updated(function (User $user): void {
            if ($user->wasChanged('is_banned')) {
                Listing::flushHomeCache();
            }
        });
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function conversations(): Builder
    {
        return Conversation::query()->forUser($this);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'seller_id');
    }

    public function averageRating(): ?float
    {
        $average = $this->reviewsReceived()->visible()->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
    }

    public function reviewsCount(): int
    {
        return $this->reviewsReceived()->visible()->count();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->relationLoaded('subscriptions')
            ? $this->subscriptions->first(fn (Subscription $s) => $s->isActive())
            : $this->subscriptions()->active()->latest('expires_at')->first();
    }

    public function adBanners(): HasMany
    {
        return $this->hasMany(AdBanner::class);
    }

    public function unreadMessagesCount(): int
    {
        return Message::query()
            ->unreadFor($this)
            ->whereHas('conversation', fn (Builder $q) => $q->forUser($this))
            ->count();
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function markPhoneAsVerified(): void
    {
        if (! $this->hasVerifiedPhone()) {
            $this->forceFill(['phone_verified_at' => now()])->save();
        }
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MODERATOR]);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->is_banned && $this->isStaff();
    }
}
