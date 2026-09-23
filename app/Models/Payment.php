<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'listing_id', 'package_id', 'subscription_id', 'ad_banner_id', 'gateway', 'amount',
        'currency', 'status', 'gateway_order_id', 'gateway_transaction_id', 'paid_at', 'meta',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function adBanner(): BelongsTo
    {
        return $this->belongsTo(AdBanner::class);
    }

    public function isForSubscription(): bool
    {
        return $this->subscription_id !== null;
    }

    public function isForAdBanner(): bool
    {
        return $this->ad_banner_id !== null;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }
}
