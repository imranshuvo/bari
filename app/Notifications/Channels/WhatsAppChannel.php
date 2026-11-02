<?php

namespace App\Notifications\Channels;

use App\Services\Waha;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private Waha $waha) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor(self::class, $notification);

        if (! $phone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $this->waha->sendText($phone, $notification->toWhatsApp($notifiable));
    }
}
