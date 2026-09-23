<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlides\Pages;

use App\Filament\Resources\HeroSlides\HeroSlideResource;
use App\Models\HeroSlide;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateHeroSlide extends CreateRecord
{
    protected static string $resource = HeroSlideResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        $path = $data['image_upload'] ?? null;

        if (is_string($path) && $path !== '') {
            $record->addMediaFromDisk($path, 'local')->toMediaCollection(HeroSlide::IMAGE);
        }

        return $record;
    }
}
