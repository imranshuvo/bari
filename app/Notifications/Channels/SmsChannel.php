<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsSender;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsSender $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor(self::class, $notification);

        if (! $phone || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sms->send($phone, $notification->toSms($notifiable));
    }
}
