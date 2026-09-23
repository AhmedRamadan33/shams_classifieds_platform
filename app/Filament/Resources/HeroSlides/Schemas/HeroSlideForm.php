<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlides\Schemas;

use App\Models\HeroSlide;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class HeroSlideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('current_image')
                    ->label(__('app.admin.current_image'))
                    ->visible(fn (?HeroSlide $record): bool => $record !== null && $record->hasMedia(HeroSlide::IMAGE))
                    ->content(fn (?HeroSlide $record) => $record !== null
                        ? new HtmlString('<img src="'.e((string) $record->imageUrl()).'" class="h-24 rounded-lg object-cover">')
                        : null),
                FileUpload::make('image_upload')
                    ->label(__('app.admin.image'))
                    ->image()
                    ->disk('local')
                    ->directory('hero-slide-uploads')
                    ->visibility('private')
                    ->required(fn (?HeroSlide $record): bool => $record === null || ! $record->hasMedia(HeroSlide::IMAGE))
                    ->dehydrated(fn ($state): bool => filled($state)),
                TextInput::make('title')
                    ->label(__('app.admin.title'))
                    ->maxLength(150),
                TextInput::make('subtitle')
                    ->label(__('app.admin.subtitle'))
                    ->maxLength(200),
                TextInput::make('link_url')
                    ->label(__('app.admin.link_url'))
                    ->url()
                    ->maxLength(500),
                TextInput::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('app.admin.is_active'))
                    ->default(true),
            ]);
    }
}
