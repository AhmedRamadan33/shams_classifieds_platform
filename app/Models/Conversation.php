<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One buyer's conversation with a listing's seller ("راسل المعلن"): exactly one per (listing, buyer)
 * pair, see the unique index. Both sides keep using the same thread instead of starting a new one.
 */
class Conversation extends Model
{
    protected $fillable = ['listing_id', 'buyer_id', 'seller_id', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id));
    }

    public function otherParticipant(User $viewer): User
    {
        return $viewer->is($this->buyer) ? $this->seller : $this->buyer;
    }

    public function isParticipant(User $user): bool
    {
        return $this->buyer_id === $user->id || $this->seller_id === $user->id;
    }
}
