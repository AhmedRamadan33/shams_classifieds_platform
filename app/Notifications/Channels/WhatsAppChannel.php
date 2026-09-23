<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppDeliveryException;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Notifications\Notification;

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
