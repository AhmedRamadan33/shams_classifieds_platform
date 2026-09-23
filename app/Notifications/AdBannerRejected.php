<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AdBanner;
use App\Models\User;
use App\Notifications\Concerns\HasUserPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdBannerRejected extends Notification
{
    use HasUserPreferredChannels;

    public function __construct(public readonly AdBanner $adBanner, public readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ad_banner_rejected',
            'ad_banner_id' => $this->adBanner->id,
            'message' => __('app.notifications.ad_banner_rejected', ['reason' => $this->reason]),
            'url' => route('ad-banners.index'),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.mail.subjects.ad_banner_rejected'))
            ->greeting(__('app.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.ad_banner_rejected', ['reason' => $this->reason]))
            ->action(__('app.mail.action'), route('ad-banners.index'))
            ->salutation(__('app.mail.salutation', ['brand' => __('app.brand')]));
    }

    public function toWhatsapp(User $notifiable): string
    {
        return __('app.notifications.ad_banner_rejected', ['reason' => $this->reason]);
    }
}
