<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;

/**
 * Every notification always creates an in-app (database) notification; e-mail and WhatsApp are
 * opt-in copies of the same event, following the recipient's profile preferences
 * (User::notify_email / notify_whatsapp), and only when the matching contact detail is present.
 */
trait HasUserPreferredChannels
{
    /**
     * @return list<string>
     */
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
