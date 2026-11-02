<?php

namespace App\Services\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;

/** Sends SMS from an Android phone running "SMS Gateway for Android" (sms-gate.app). */
class SmsGate implements SmsSender
{
    public function isConfigured(): bool
    {
        return filled(config('services.sms_gate.username')) && filled(config('services.sms_gate.password'));
    }

    public function send(string $phone, string $text): void
    {
        Http::withBasicAuth((string) config('services.sms_gate.username'), (string) config('services.sms_gate.password'))
            ->acceptJson()
            ->timeout(20)
            ->post(rtrim((string) config('services.sms_gate.url'), '/').'/messages', [
                'textMessage' => ['text' => $text],
                'phoneNumbers' => [PhoneNumber::normalize($phone)],
            ])
            ->throw();
    }
}
