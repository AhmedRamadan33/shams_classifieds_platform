<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListingEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['listing_id', 'type'];

    protected function casts(): array
    {
        return [
            'type' => ListingEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
