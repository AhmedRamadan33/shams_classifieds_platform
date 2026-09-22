<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Pages;

use App\Filament\Resources\StaticPages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
