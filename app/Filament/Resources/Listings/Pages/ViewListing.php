<?php

declare(strict_types=1);

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\Actions\ListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewListing extends ViewRecord
{
    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_public')
                ->label(__('app.admin.open_public'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Listing $record): string => $record->url(), shouldOpenInNewTab: true),
            ListingActions::approve()->after(fn () => $this->refreshFormData(['status', 'rejection_reason', 'published_at', 'expires_at'])),
            ListingActions::reject()->after(fn () => $this->refreshFormData(['status', 'rejection_reason'])),
            ListingActions::feature(),
            DeleteAction::make()->successRedirectUrl(fn (): string => ListingResource::getUrl('index')),
        ];
    }
}
