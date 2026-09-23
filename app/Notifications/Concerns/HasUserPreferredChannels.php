<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;

trait HasUserPreferredChannels
{
    protected function preferredChannels(User $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->notify_email && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        if ($notifiable->notify_whatsapp && filled($notifiable->phone)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }
}
