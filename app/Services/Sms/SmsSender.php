<?php

namespace App\Services\Sms;

/** Anything that can send a text message. Swap the binding in AppServiceProvider to change provider. */
interface SmsSender
{
    public function isConfigured(): bool;

    public function send(string $phone, string $text): void;
}
