<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

class UserIconAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            .'<rect width="32" height="32" fill="#fed7aa"/>'
            .'<circle cx="16" cy="12.5" r="5" fill="#c2410c"/>'
            .'<path d="M6 28c0-5.6 4.4-9 10-9s10 3.4 10 9z" fill="#c2410c"/>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
