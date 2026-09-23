<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use App\Notifications\Concerns\HasUserPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageReceived extends Notification
{
    use HasUserPreferredChannels;

    public function __construct(public readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        $conversation = $this->message->conversation;

        return [
            'type' => 'new_message',
            'conversation_id' => $conversation->id,
            'title' => $conversation->listing->title,
            'message' => __('app.notifications.new_message', [
                'name' => $this->message->sender->name,
                'title' => $conversation->listing->title,
            ]),
            'url' => route('messages.show', $conversation),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $conversation = $this->message->conversation;

        return (new MailMessage)
            ->subject(__('app.mail.subjects.new_message', ['brand' => __('app.brand')]))
            ->greeting(__('app.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.new_message', ['name' => $this->message->sender->name, 'title' => $conversation->listing->title]))
            ->line($this->message->body)
            ->action(__('app.mail.action'), route('messages.show', $conversation))
            ->salutation(__('app.mail.salutation', ['brand' => __('app.brand')]));
    }

    public function toWhatsapp(User $notifiable): string
    {
        $conversation = $this->message->conversation;

        return __('app.notifications.new_message', ['name' => $this->message->sender->name, 'title' => $conversation->listing->title])
            ."\n".$this->message->body;
    }
}
