<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller's branded page at /store/{slug}. Anyone can create one; it is shown as an active,
 * "متجر مميز" store only while its owner has an active Subscription (see User::activeSubscription()).
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'slug', 'bio'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->user->activeSubscription() !== null;
    }

    public function url(): string
    {
        return route('stores.show', $this->slug);
    }
}
