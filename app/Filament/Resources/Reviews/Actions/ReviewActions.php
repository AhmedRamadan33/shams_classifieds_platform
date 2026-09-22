<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Actions;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class ReviewActions
{
    public static function hide(): Action
    {
        return Action::make('hide')
            ->label(__('app.admin.hide'))
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->visible(fn (Review $record): bool => ! $record->is_hidden)
            ->requiresConfirmation()
            ->action(function (Review $record): void {
                $record->update(['is_hidden' => true]);

                Notification::make()->title(__('app.admin.hidden_done'))->success()->send();
            });
    }

    public static function unhide(): Action
    {
        return Action::make('unhide')
            ->label(__('app.admin.unhide'))
            ->icon(Heroicon::OutlinedEye)
            ->color('success')
            ->visible(fn (Review $record): bool => $record->is_hidden)
            ->action(function (Review $record): void {
                $record->update(['is_hidden' => false]);

                Notification::make()->title(__('app.admin.unhidden_done'))->success()->send();
            });
    }
}
