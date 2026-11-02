<?php

namespace App\Support;

class PhoneNumber
{
    private const BANGLA_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    /**
     * "+880 1712-345678", "00880…", "01712345678" or Bangla digits → "+8801712345678".
     * A local number (one leading 0) gets the default country code from config('planner.phone_country_code').
     */
    public static function normalize(?string $input): string
    {
        $value = self::clean($input);
        $digits = preg_replace('/\D/', '', $value);

        $code = self::countryCode();

        if (str_starts_with($value, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($value, '0')) {
            $digits = $code.substr($digits, 1);
        }

        // "+880 01712…": the local 0 written after the default country code.
        if (str_starts_with($digits, $code.'0')) {
            $digits = $code.substr($digits, strlen($code) + 1);
        }

        return '+'.$digits;
    }

    /** Starts with +, 00 or a local 0, and has 8–15 digits with the country code. */
    public static function isValid(?string $input): bool
    {
        $value = self::clean($input);

        if (! preg_match('/^(\+|0)[\d\s\-().]+$/', $value)) {
            return false;
        }

        $digits = strlen(self::normalize($value)) - 1;

        return $digits >= 8 && $digits <= 15;
    }

    /** Anything without an @ is treated as a phone number. */
    public static function looksLikePhone(string $input): bool
    {
        return ! str_contains($input, '@');
    }

    /** "+8801712345678" → "+880 17•• •••678", for showing where a code went. */
    public static function mask(string $phone): string
    {
        $digits = ltrim(self::normalize($phone), '+');
        $code = self::countryCode();
        $codeLength = str_starts_with($digits, $code) ? strlen($code) : 2;
        $national = substr($digits, $codeLength);
        $hidden = max(strlen($national) - 5, 0);

        return '+'.substr($digits, 0, $codeLength).' '.substr($national, 0, 2)
            .str_repeat('•', min($hidden, 2)).' '.str_repeat('•', max($hidden - 2, 0)).substr($national, -3);
    }

    /** Digits only, as WhatsApp wants them: "8801712345678". */
    public static function digits(string $phone): string
    {
        return ltrim(self::normalize($phone), '+');
    }

    /** Bangla digits (০–৯) → 0–9, everything else unchanged. */
    public static function asciiDigits(string $input): string
    {
        return strtr($input, self::BANGLA_DIGITS);
    }

    private static function clean(?string $input): string
    {
        return self::asciiDigits(trim((string) $input));
    }

    private static function countryCode(): string
    {
        return preg_replace('/\D/', '', (string) config('planner.phone_country_code', '880'));
    }
}
