<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A static page (about, terms, privacy...) managed from the admin panel and served at /p/{slug}.
 */
class Page extends Model
{
    public const FOOTER_CACHE_KEY = 'pages.footer';

    protected $fillable = ['slug', 'title', 'body', 'is_published', 'sort_order'];

    protected $attributes = [
        'is_published' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::FOOTER_CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Title/slug pairs of the published pages, for the footer links (cached).
     *
     * @return array<int, array{slug: string, title: string}>
     */
    public static function footerLinks(): array
    {
        return Cache::rememberForever(self::FOOTER_CACHE_KEY, fn () => self::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['slug', 'title'])
            ->map(fn (Page $page) => ['slug' => $page->slug, 'title' => $page->title])
            ->all());
    }
}
