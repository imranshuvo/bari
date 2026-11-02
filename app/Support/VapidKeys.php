<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\VAPID;

/**
 * The key pair that identifies this server to push services.
 * VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY win when set; otherwise a pair is made once and kept in
 * storage/app/private/vapid.json (on the app-files volume, so it survives redeploys).
 */
class VapidKeys
{
    private static ?array $keys = null;

    /** @return array{publicKey: string, privateKey: string} */
    public static function get(): array
    {
        if (self::$keys) {
            return self::$keys;
        }

        $public = config('services.webpush.public_key');
        $private = config('services.webpush.private_key');

        if (filled($public) && filled($private)) {
            return self::$keys = ['publicKey' => $public, 'privateKey' => $private];
        }

        $disk = Storage::disk('local');

        if (! $disk->exists('vapid.json')) {
            $disk->put('vapid.json', json_encode(VAPID::createVapidKeys()));
        }

        $stored = json_decode((string) $disk->get('vapid.json'), true);

        return self::$keys = ['publicKey' => $stored['publicKey'], 'privateKey' => $stored['privateKey']];
    }

    public static function forget(): void
    {
        self::$keys = null;
    }
}
