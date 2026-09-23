<?php

declare(strict_types=1);

namespace App\Filament\Resources\Listings\Actions;

use App\Actions\ApproveListing;
use App\Actions\RejectListing;
use App\Enums\ListingStatus;
use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

final class ListingActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('app.admin.approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Listing $record): bool => in_array($record->status, [ListingStatus::Pending, ListingStatus::Rejected], true))
            ->requiresConfirmation()
            ->modalHeading(__('app.admin.approve_heading'))
            ->modalDescription(__('app.admin.approve_description'))
            ->action(function (Listing $record): void {
                app(ApproveListing::class)($record);

                Notification::make()->title(__('app.admin.approved_done'))->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('app.admin.reject'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Listing $record): bool => in_array($record->status, [ListingStatus::Pending, ListingStatus::Active], true))
            ->modalHeading(__('app.admin.reject_heading'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('app.admin.rejection_reason'))
                    ->helperText(__('app.admin.rejection_reason_help'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(4),
            ])
            ->action(function (Listing $record, array $data): void {
                app(RejectListing::class)($record, $data['reason']);

                Notification::make()->title(__('app.admin.rejected_done'))->success()->send();
            });
    }

    public static function feature(): Action
    {
        return Action::make('feature')
            ->label(__('app.admin.feature'))
            ->icon(Heroicon::OutlinedStar)
            ->color('warning')
            ->modalHeading(__('app.admin.feature_heading'))
            ->fillForm(fn (Listing $record): array => ['featured_until' => $record->featured_until])
            ->schema([
                DateTimePicker::make('featured_until')
                    ->label(__('app.admin.featured_until'))
                    ->helperText(__('app.admin.featured_until_help'))
                    ->seconds(false),
            ])
            ->action(function (Listing $record, array $data): void {
                $record->update(['featured_until' => $data['featured_until'] ?? null]);

                Notification::make()->title(__('app.admin.featured_done'))->success()->send();
            });
    }

    public static function bulkApprove(): BulkAction
    {
        return BulkAction::make('bulk_approve')
            ->label(__('app.admin.approve_selected'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $approved = 0;

                foreach ($records as $listing) {
                    if (in_array($listing->status, [ListingStatus::Pending, ListingStatus::Rejected], true)) {
                        app(ApproveListing::class)($listing);
                        $approved++;
                    }
                }

                Notification::make()->title(__('app.admin.approved_count', ['count' => $approved]))->success()->send();
            });
    }
}
