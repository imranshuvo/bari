<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\PushService;
use Illuminate\Notifications\Notification;

/** Sends a notification's toWebPush() payload to every device the user turned notifications on for. */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        $push = app(PushService::class);

        foreach ($notifiable->pushSubscriptions as $subscription) {
            if (! $push->send($subscription, $payload)) {
                $subscription->delete();
            }
        }
    }
}
