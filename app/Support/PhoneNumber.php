<?php

namespace App\Support;

class PhoneNumber
{
    private const BANGLA_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    /** "+880 1712-345678", "00880…" or Bangla digits → "+8801712345678". */
    public static function normalize(?string $input): string
    {
        $value = strtr(trim((string) $input), self::BANGLA_DIGITS);
        $digits = preg_replace('/\D/', '', $value);

        if (str_starts_with($value, '00')) {
            $digits = substr($digits, 2);
        }

        return '+'.$digits;
    }

    /** Must start with + or 00 (country code) and have 8–15 digits. */
    public static function isValid(?string $input): bool
    {
        $value = strtr(trim((string) $input), self::BANGLA_DIGITS);

        if (! preg_match('/^(\+|00)[\d\s\-().]+$/', $value)) {
            return false;
        }

        $digits = strlen(self::normalize($value)) - 1;

        return $digits >= 8 && $digits <= 15;
    }

    /** Digits only, as WhatsApp wants them: "8801712345678". */
    public static function digits(string $phone): string
    {
        return ltrim(self::normalize($phone), '+');
    }
}
