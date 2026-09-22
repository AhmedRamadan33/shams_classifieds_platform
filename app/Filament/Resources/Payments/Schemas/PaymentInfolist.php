<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentStatus;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('app.admin.payment'))
                    ->columns(2)
                    ->components([
                        TextEntry::make('user.name')->label(__('app.admin.owner')),
                        TextEntry::make('listing.title')->label(__('app.admin.listing')),
                        TextEntry::make('package.name')->label(__('app.admin.package')),
                        TextEntry::make('amount')
                            ->label(__('app.admin.amount'))
                            ->formatStateUsing(fn ($record) => number_format((float) $record->amount, 2).' '.$record->currency),
                        TextEntry::make('gateway')->label(__('app.admin.gateway')),
                        TextEntry::make('status')
                            ->label(__('app.admin.status'))
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                            ->color(fn (PaymentStatus $state): string => $state->filamentColor()),
                        TextEntry::make('gateway_order_id')->label(__('app.admin.gateway_order_id'))->placeholder('—'),
                        TextEntry::make('gateway_transaction_id')->label('Transaction ID')->placeholder('—'),
                        TextEntry::make('paid_at')->label(__('app.admin.paid_at'))->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->label(__('app.admin.created_at'))->dateTime(),
                    ]),
                KeyValueEntry::make('meta')
                    ->label('Meta')
                    ->columnSpanFull()
                    ->visible(fn ($record) => filled($record->meta)),
            ]);
    }
}
