<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Support\VapidKeys;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

/** Sends one app notification to one device through its browser's push service. */
class PushService
{
    private ?WebPush $webPush = null;

    /** False when the push service says the device is gone (unsubscribed or app removed). */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        $report = $this->webPush()->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]),
            json_encode($payload),
            ['TTL' => 86400, 'urgency' => 'normal'],
        );

        if ($report->isSubscriptionExpired()) {
            return false;
        }

        if (! $report->isSuccess()) {
            report(new RuntimeException('App notification failed: '.$report->getReason()));
        }

        return true;
    }

    private function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush(['VAPID' => [
            'subject' => config('app.url') ?: 'mailto:noreply@example.com',
            ...VapidKeys::get(),
        ]]);
    }
}
