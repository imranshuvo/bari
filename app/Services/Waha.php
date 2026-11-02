<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;

/** Sends WhatsApp messages through a self-hosted WAHA server. */
class Waha
{
    public static function isConfigured(): bool
    {
        return filled(config('services.waha.url'));
    }

    public function sendText(string $phone, string $text): void
    {
        Http::baseUrl(rtrim((string) config('services.waha.url'), '/'))
            ->withHeaders(array_filter(['X-Api-Key' => config('services.waha.key')]))
            ->acceptJson()
            ->timeout(20)
            ->post('/api/sendText', [
                'session' => config('services.waha.session') ?: 'default',
                'chatId' => PhoneNumber::digits($phone).'@c.us',
                'text' => $text,
            ])
            ->throw();
    }
}
