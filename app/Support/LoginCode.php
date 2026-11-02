<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

use function Illuminate\Support\defer;

/**
 * Six-digit codes sent by WhatsApp (SMS as backup) to sign in or to confirm a phone number.
 * Codes last 10 minutes and allow 5 tries. Sending is limited per number (1/minute, 5/hour,
 * 10/day) and per IP address (20/hour).
 */
class LoginCode
{
    private const LIFETIME = 600;

    private const MAX_TRIES = 5;

    /**
     * Sends a code, unless a limit is reached or (for sign-in) no account has this number.
     * Either way nothing is returned, so the caller can't tell whether the number has an account.
     */
    public static function send(string $phone, ?string $ip = null, string $purpose = 'login'): void
    {
        $phone = PhoneNumber::normalize($phone);

        if (self::limited($phone, $ip, $purpose)) {
            return;
        }

        $user = User::where('phone', $phone)->first();

        if ($purpose === 'login' && ! $user) {
            return;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::key($phone, $purpose), [
            'hash' => self::hash($code),
            'expires_at' => now()->addSeconds(self::LIFETIME)->getTimestamp(),
        ], self::LIFETIME);
        Cache::put(self::key($phone, $purpose).':tries', 0, self::LIFETIME);

        $text = __('Your Bari code is :code. It expires in 10 minutes.', ['code' => $code], $user?->locale ?? app()->getLocale());

        // Sent after the response, so a known number answers as fast as an unknown one.
        defer(fn () => PhoneMessenger::send($phone, $text));
    }

    /** True once the right code is given; the code can't be used again. */
    public static function verifyCode(string $phone, string $code, string $purpose = 'login'): bool
    {
        $key = self::key(PhoneNumber::normalize($phone), $purpose);
        $entry = Cache::get($key);

        if (! $entry || $entry['expires_at'] < now()->getTimestamp()) {
            return false;
        }

        // Counted atomically, so parallel guesses can't share one try.
        $tries = Cache::increment($key.':tries');

        if ($tries > self::MAX_TRIES) {
            Cache::forget($key);

            return false;
        }

        if (! hash_equals($entry['hash'], self::hash(preg_replace('/\D/', '', PhoneNumber::asciiDigits($code))))) {
            if ($tries >= self::MAX_TRIES) {
                Cache::forget($key);
            }

            return false;
        }

        Cache::forget($key);
        Cache::forget($key.':tries');

        return true;
    }

    /** The account for this number, if the code is right. */
    public static function verify(string $phone, string $code): ?User
    {
        return self::verifyCode($phone, $code) ? User::where('phone', PhoneNumber::normalize($phone))->first() : null;
    }

    public static function secondsUntilResend(string $phone, string $purpose = 'login'): int
    {
        $key = 'login-code-minute:'.$purpose.':'.PhoneNumber::normalize($phone);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    private static function limited(string $phone, ?string $ip, string $purpose): bool
    {
        $limits = [
            "login-code-minute:{$purpose}:{$phone}" => [1, 60],
            "login-code-hour:{$purpose}:{$phone}" => [5, 3600],
            "login-code-day:{$purpose}:{$phone}" => [10, 86400],
        ];

        if ($ip) {
            $limits["login-code-ip:{$ip}"] = [20, 3600];
        }

        foreach ($limits as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return true;
            }
        }

        foreach ($limits as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        return false;
    }

    private static function key(string $phone, string $purpose): string
    {
        return "login-code:{$purpose}:{$phone}";
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
