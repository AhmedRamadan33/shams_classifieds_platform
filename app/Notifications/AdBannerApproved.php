<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AdBanner;
use App\Models\User;
use App\Notifications\Concerns\HasUserPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdBannerApproved extends Notification
{
    use HasUserPreferredChannels;

    public function __construct(public readonly AdBanner $adBanner) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ad_banner_approved',
            'ad_banner_id' => $this->adBanner->id,
            'message' => __('app.notifications.ad_banner_approved'),
            'url' => route('ad-banners.index'),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.mail.subjects.ad_banner_approved'))
            ->greeting(__('app.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.ad_banner_approved'))
            ->action(__('app.mail.action'), route('ad-banners.index'))
            ->salutation(__('app.mail.salutation', ['brand' => __('app.brand')]));
    }

    public function toWhatsapp(User $notifiable): string
    {
        return __('app.notifications.ad_banner_approved')."\n".route('ad-banners.index');
    }
}
