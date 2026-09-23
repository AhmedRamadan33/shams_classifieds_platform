<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldType;
use App\Observers\CategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(CategoryObserver::class)]
class CategoryField extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'key',
        'type',
        'options',
        'unit',
        'is_required',
        'is_filterable',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (CategoryField $field): void {
            if ($field->type !== FieldType::Select) {
                $field->options = null;
            }

            if ($field->type !== FieldType::Number) {
                $field->unit = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function optionValues(): array
    {
        return $this->type === FieldType::Select
            ? array_values(array_map('strval', $this->options ?? []))
            : [];
    }

    public function toFormArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type->value,
            'options' => $this->optionValues(),
            'unit' => $this->unit,
            'is_required' => $this->is_required,
        ];
    }
}
