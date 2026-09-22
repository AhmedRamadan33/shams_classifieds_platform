<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Actions;

use App\Enums\ReportStatus;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Handling a report: resolve it, dismiss it, or remove the reported listing and resolve.
 */
final class ReportActions
{
    public static function resolve(): Action
    {
        return Action::make('resolve')
            ->label(__('app.admin.resolve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
            ->action(fn (Report $record) => self::close($record, ReportStatus::Resolved));
    }

    public static function dismiss(): Action
    {
        return Action::make('dismiss')
            ->label(__('app.admin.dismiss'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
            ->action(fn (Report $record) => self::close($record, ReportStatus::Dismissed));
    }

    /**
     * Soft-delete the reported listing and mark every open report about it as resolved.
     */
    public static function removeListing(): Action
    {
        return Action::make('remove_listing')
            ->label(__('app.admin.remove_listing'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open && $record->listing !== null && ! $record->listing->trashed())
            ->requiresConfirmation()
            ->modalHeading(__('app.admin.remove_listing_heading'))
            ->modalDescription(__('app.admin.remove_listing_description'))
            ->action(function (Report $record): void {
                $record->listing->delete();

                Report::query()
                    ->where('listing_id', $record->listing_id)
                    ->where('status', ReportStatus::Open->value)
                    ->update(['status' => ReportStatus::Resolved->value, 'handled_by' => auth()->id()]);

                Notification::make()->title(__('app.admin.listing_removed'))->success()->send();
            });
    }

    private static function close(Report $report, ReportStatus $status): void
    {
        $report->forceFill(['status' => $status, 'handled_by' => auth()->id()])->save();

        Notification::make()->title(__('app.admin.report_updated'))->success()->send();
    }
}
