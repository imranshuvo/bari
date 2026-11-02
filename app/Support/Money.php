<?php

namespace App\Support;

use NumberFormatter;

/**
 * Money is stored as integer minor units (øre, paisa, cents).
 */
class Money
{
    public const CURRENCIES = ['BDT', 'DKK', 'EUR', 'USD', 'GBP', 'SEK', 'NOK', 'INR'];

    /** [symbol, placed before the number?] */
    private const SYMBOLS = [
        'BDT' => ['৳', true],
        'INR' => ['₹', true],
        'EUR' => ['€', true],
        'USD' => ['$', true],
        'GBP' => ['£', true],
        'DKK' => ['kr.', false],
        'SEK' => ['kr', false],
        'NOK' => ['kr', false],
    ];

    private const BANGLA_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    public static function format(?int $minor, string $currency, ?string $locale = null): string
    {
        $minor ??= 0;
        $locale ??= app()->getLocale();

        $formatter = new NumberFormatter(self::numberLocale($currency, $locale), NumberFormatter::DECIMAL);
        $decimals = $minor % 100 === 0 ? 0 : 2;
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        $number = $formatter->format(abs($minor) / 100);
        [$symbol, $before] = self::SYMBOLS[$currency] ?? [$currency, false];
        $text = $before ? $symbol.$number : $number.' '.$symbol;

        return ($minor < 0 ? '−' : '').$text;
    }

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[$currency][0] ?? $currency;
    }

    /** Minor units to a plain editable string, e.g. 18500050 → "185000.50". */
    public static function toInput(?int $minor): ?string
    {
        if ($minor === null) {
            return null;
        }

        return $minor % 100 === 0
            ? (string) intdiv($minor, 100)
            : number_format($minor / 100, 2, '.', '');
    }

    /**
     * Parse what a person typed into minor units.
     * Accepts Bangla digits, "1,85,000", "185.000,50", "185000.5", "৳ 5,000".
     */
    public static function parse(mixed $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (is_int($input) || is_float($input)) {
            return (int) round($input * 100);
        }

        $value = strtr(trim((string) $input), self::BANGLA_DIGITS);
        $negative = str_starts_with($value, '-') || str_starts_with($value, '−');
        $value = preg_replace('/[^0-9.,]/u', '', $value);

        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Both used: the last one is the decimal separator.
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $decimalSeparator = self::looksDecimal($value, ',') ? ',' : null;
        } elseif ($lastDot !== false) {
            $decimalSeparator = self::looksDecimal($value, '.') ? '.' : null;
        } else {
            $decimalSeparator = null;
        }

        if ($decimalSeparator === null) {
            $normalised = str_replace([',', '.'], '', $value);
        } else {
            $groupSeparator = $decimalSeparator === ',' ? '.' : ',';
            $normalised = str_replace($groupSeparator, '', $value);
            $normalised = str_replace($decimalSeparator, '.', $normalised);
        }

        if (! is_numeric($normalised)) {
            return null;
        }

        $minor = (int) round(((float) $normalised) * 100);

        return $negative ? -$minor : $minor;
    }

    /** A single separator followed by 1–2 digits is a decimal mark; otherwise it groups thousands. */
    private static function looksDecimal(string $value, string $separator): bool
    {
        if (substr_count($value, $separator) !== 1) {
            return false;
        }

        $after = substr($value, strrpos($value, $separator) + 1);

        return strlen($after) >= 1 && strlen($after) <= 2;
    }

    /** VAT part of an amount that already includes VAT. */
    public static function vatPart(int $grossMinor, float|string $rate): int
    {
        $rate = (float) $rate;

        if ($rate <= 0) {
            return 0;
        }

        return (int) round($grossMinor * $rate / (100 + $rate));
    }

    private static function numberLocale(string $currency, string $locale): string
    {
        if ($locale === 'bn') {
            return 'bn_BD';
        }

        return match ($currency) {
            'BDT', 'INR' => 'en_IN',
            'DKK' => 'da_DK',
            'SEK' => 'sv_SE',
            'NOK' => 'nb_NO',
            'EUR' => 'en_IE',
            'GBP' => 'en_GB',
            default => 'en_US',
        };
    }
}
