<?php

namespace App\Enums;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Sms\SmsSender;
use App\Services\Waha;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum NotificationChannel: string implements HasIcon, HasLabel
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Sms => __('SMS'),
            self::Email => __('Email'),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::WhatsApp => Heroicon::OutlinedChatBubbleLeftRight,
            self::Sms => Heroicon::OutlinedDevicePhoneMobile,
            self::Email => Heroicon::OutlinedEnvelope,
        };
    }

    /** The Laravel notification channel that delivers it. */
    public function driver(): string
    {
        return match ($this) {
            self::WhatsApp => WhatsAppChannel::class,
            self::Sms => SmsChannel::class,
            self::Email => 'mail',
        };
    }

    public function isPhone(): bool
    {
        return $this !== self::Email;
    }

    /** False when the server has no settings for this channel yet. */
    public function isConfigured(): bool
    {
        return match ($this) {
            self::WhatsApp => Waha::isConfigured(),
            self::Sms => app(SmsSender::class)->isConfigured(),
            self::Email => true,
        };
    }
}
