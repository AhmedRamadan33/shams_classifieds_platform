<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\GeographyObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(GeographyObserver::class)]
class City extends Model
{
    use HasFactory;

    protected $fillable = ['governorate_id', 'name', 'slug', 'sort_order'];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }
}
