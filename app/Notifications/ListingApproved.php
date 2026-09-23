<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\Concerns\HasUserPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingApproved extends Notification
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
            'type' => 'approved',
            'listing_id' => $this->listing->id,
            'title' => $this->listing->title,
            'message' => __('app.notifications.approved', ['title' => $this->listing->title]),
            'url' => $this->listing->url(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.mail.subjects.approved'))
            ->greeting(__('app.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.approved', ['title' => $this->listing->title]))
            ->action(__('app.mail.action'), $this->listing->url())
            ->salutation(__('app.mail.salutation', ['brand' => __('app.brand')]));
    }

    public function toWhatsapp(User $notifiable): string
    {
        return __('app.notifications.approved', ['title' => $this->listing->title])."\n".$this->listing->url();
    }
}
