<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\Concerns\HasUserPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the owner when a moderator rejects their listing; carries the reason.
 */
class ListingRejected extends Notification
{
    use HasUserPreferredChannels;

    public function __construct(public readonly Listing $listing, public readonly string $reason) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rejected',
            'listing_id' => $this->listing->id,
            'title' => $this->listing->title,
            'message' => __('app.notifications.rejected', ['title' => $this->listing->title, 'reason' => $this->reason]),
            'url' => route('dashboard', ['status' => 'rejected']),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.mail.subjects.rejected'))
            ->greeting(__('app.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.rejected', ['title' => $this->listing->title, 'reason' => $this->reason]))
            ->action(__('app.mail.action'), route('dashboard', ['status' => 'rejected']))
            ->salutation(__('app.mail.salutation', ['brand' => __('app.brand')]));
    }

    public function toWhatsapp(User $notifiable): string
    {
        return __('app.notifications.rejected', ['title' => $this->listing->title, 'reason' => $this->reason]);
    }
}
