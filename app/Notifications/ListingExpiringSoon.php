<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\Concerns\HasUserPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingExpiringSoon extends Notification
{
    use HasUserPreferredChannels;

    public function __construct(public readonly Listing $listing) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expiring',
            'listing_id' => $this->listing->id,
            'title' => $this->listing->title,
            'message' => __('app.notifications.expiring', [
                'title' => $this->listing->title,
                'date' => $this->listing->expires_at?->translatedFormat('j F Y'),
            ]),
            'url' => route('dashboard', ['status' => 'active']),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = __('app.notifications.expiring', [
            'title' => $this->listing->title,
            'date' => $this->listing->expires_at?->translatedFormat('j F Y'),
        ]);

        return (new MailMessage)
            ->subject(__('app.mail.subjects.expiring'))
            ->greeting(__('app.mail.greeting', ['name' => $notifiable->name]))
            ->line($message)
            ->action(__('app.mail.action'), route('dashboard', ['status' => 'active']))
            ->salutation(__('app.mail.salutation', ['brand' => __('app.brand')]));
    }

    public function toWhatsapp(User $notifiable): string
    {
        return __('app.notifications.expiring', [
            'title' => $this->listing->title,
            'date' => $this->listing->expires_at?->translatedFormat('j F Y'),
        ]);
    }
}
