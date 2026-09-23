<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlides\Pages;

use App\Filament\Resources\HeroSlides\HeroSlideResource;
use App\Models\HeroSlide;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditHeroSlide extends EditRecord
{
    protected static string $resource = HeroSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $path = $data['image_upload'] ?? null;

        if (is_string($path) && $path !== '') {
            $record->addMediaFromDisk($path, 'local')->toMediaCollection(HeroSlide::IMAGE);
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
