<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\Concerns\HasUserPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by searches:notify when a saved search with notify=true has new matches since it was last
 * checked.
 */
class SavedSearchMatched extends Notification
{
    use HasUserPreferredChannels;

    public function __construct(public readonly SavedSearch $savedSearch, public readonly int $count) {}

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
            'type' => 'saved_search_matched',
            'saved_search_id' => $this->savedSearch->id,
            'title' => $this->savedSearch->name,
            'message' => __('app.notifications.saved_search_matched', ['name' => $this->savedSearch->name, 'count' => $this->count]),
            'url' => $this->savedSearch->url(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.mail.subjects.saved_search_matched'))
            ->greeting(__('app.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.saved_search_matched', ['name' => $this->savedSearch->name, 'count' => $this->count]))
            ->action(__('app.mail.action'), $this->savedSearch->url())
            ->salutation(__('app.mail.salutation', ['brand' => __('app.brand')]));
    }

    public function toWhatsapp(User $notifiable): string
    {
        return __('app.notifications.saved_search_matched', ['name' => $this->savedSearch->name, 'count' => $this->count])
            ."\n".$this->savedSearch->url();
    }
}
