<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppDeliveryException;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Notifications\Notification;

/**
 * A Laravel custom notification channel: `->notify()` calls this when 'whatsapp' (self::class) is
 * in a notification's via(). The notification must define toWhatsapp($notifiable): string.
 *
 * A delivery failure is logged and swallowed, not thrown: WhatsApp is always an extra copy of an
 * in-app (database) notification the user already has, so one failed message must never turn into a
 * 500 for the request that triggered it (e.g. a moderator approving a listing).
 */
final class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsapp') || blank($notifiable->phone)) {
            return;
        }

        $message = (string) $notification->toWhatsapp($notifiable);

        try {
            $this->gateway->send($notifiable->phone, $message);
        } catch (WhatsAppDeliveryException $e) {
            report($e);
        }
    }
}
