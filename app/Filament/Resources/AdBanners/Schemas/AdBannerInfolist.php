<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdBanners\Schemas;

use App\Enums\AdBannerStatus;
use App\Enums\AdPlacement;
use App\Models\AdBanner;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdBannerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('app.admin.ad_banner'))
                    ->columns(2)
                    ->columnSpan(2)
                    ->components([
                        TextEntry::make('status')
                            ->label(__('app.admin.status'))
                            ->badge()
                            ->formatStateUsing(fn (AdBannerStatus $state): string => $state->label())
                            ->color(fn (AdBannerStatus $state): string => $state->filamentColor()),
                        TextEntry::make('rejection_reason')
                            ->label(__('app.admin.rejection_reason'))
                            ->placeholder('—')
                            ->visible(fn (AdBanner $record): bool => $record->status === AdBannerStatus::Rejected),
                        TextEntry::make('placement')
                            ->label(__('app.admin.placement'))
                            ->formatStateUsing(fn (AdPlacement $state): string => $state->label()),
                        TextEntry::make('title')->label(__('app.admin.title'))->placeholder('—'),
                        TextEntry::make('target_url')
                            ->label(__('app.admin.link_url'))
                            ->columnSpanFull()
                            ->extraAttributes(['dir' => 'ltr']),
                        ImageEntry::make('image')
                            ->label(__('app.admin.image'))
                            ->getStateUsing(fn (AdBanner $record): ?string => $record->imageUrl())
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.admin.details'))
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('user.name')->label(__('app.admin.advertiser')),
                        TextEntry::make('user.phone')
                            ->label(__('app.admin.phone'))
                            ->extraAttributes(['dir' => 'ltr']),
                        TextEntry::make('adPackage.name')->label(__('app.admin.ad_package'))->placeholder('—'),
                        TextEntry::make('clicks')->label(__('app.admin.clicks'))->numeric(),
                        TextEntry::make('starts_at')->label(__('app.admin.starts_at'))->dateTime()->placeholder('—'),
                        TextEntry::make('expires_at')->label(__('app.admin.expires_at'))->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->label(__('app.admin.created_at'))->dateTime(),
                    ]),
            ]);
    }
}
