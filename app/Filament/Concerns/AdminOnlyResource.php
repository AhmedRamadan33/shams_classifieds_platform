<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Restricts a Filament resource to users with the "admin" role. Moderators can still enter the
 * panel (ListingResource, ReportResource) but never see or reach these resources.
 */
trait AdminOnlyResource
{
    public static function canViewAny(): bool
    {
        return static::isAdmin();
    }

    public static function canCreate(): bool
    {
        return static::isAdmin();
    }

    public static function canView(Model $record): bool
    {
        return static::isAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return static::isAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return static::isAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return static::isAdmin();
    }

    private static function isAdmin(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
