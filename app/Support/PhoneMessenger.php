<?php

namespace App\Support;

use App\Enums\NotificationChannel;
use App\Services\Sms\SmsSender;
use App\Services\Waha;
use Throwable;

/** Sends one text straight away (invitations, sign-in codes): WhatsApp first, then SMS. */
class PhoneMessenger
{
    public static function isAvailable(): bool
    {
        return NotificationChannel::WhatsApp->isConfigured() || NotificationChannel::Sms->isConfigured();
    }

    /** Returns the channel that delivered the text, or null if none could. Never throws. */
    public static function send(string $phone, string $text): ?NotificationChannel
    {
        $senders = [
            NotificationChannel::WhatsApp->value => fn () => app(Waha::class)->sendText($phone, $text),
            NotificationChannel::Sms->value => fn () => app(SmsSender::class)->send(PhoneNumber::normalize($phone), $text),
        ];

        foreach ($senders as $channel => $sendWith) {
            $channel = NotificationChannel::from($channel);

            if (! $channel->isConfigured()) {
                continue;
            }

            try {
                $sendWith();

                return $channel;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return null;
    }
}
