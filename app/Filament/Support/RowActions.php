<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\ActionGroup;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

final class RowActions
{
    public static function group(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->label(__('app.admin.actions_menu'))
            ->icon(Heroicon::ChevronDown)
            ->iconPosition(IconPosition::After)
            ->button()
            ->color('gray')
            ->size(Size::Small);
    }
}
