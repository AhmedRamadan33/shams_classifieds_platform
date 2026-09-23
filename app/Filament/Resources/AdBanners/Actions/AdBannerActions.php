<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdBanners\Actions;

use App\Actions\ApproveAdBanner;
use App\Actions\RejectAdBanner;
use App\Enums\AdBannerStatus;
use App\Models\AdBanner;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class AdBannerActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('app.admin.approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (AdBanner $record): bool => in_array($record->status, [AdBannerStatus::Pending, AdBannerStatus::Rejected], true))
            ->requiresConfirmation()
            ->modalHeading(__('app.admin.approve_heading'))
            ->action(function (AdBanner $record): void {
                app(ApproveAdBanner::class)($record);

                Notification::make()->title(__('app.admin.approved_done'))->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('app.admin.reject'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (AdBanner $record): bool => in_array($record->status, [AdBannerStatus::Pending, AdBannerStatus::Approved], true))
            ->modalHeading(__('app.admin.reject_heading'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('app.admin.rejection_reason'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(4),
            ])
            ->action(function (AdBanner $record, array $data): void {
                app(RejectAdBanner::class)($record, $data['reason']);

                Notification::make()->title(__('app.admin.rejected_done'))->success()->send();
            });
    }
}
