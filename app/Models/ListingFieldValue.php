<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingFieldValue extends Model
{
    protected $fillable = ['listing_id', 'category_field_id', 'value'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CategoryField::class, 'category_field_id');
    }
}
