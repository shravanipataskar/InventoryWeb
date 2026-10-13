<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact 2-decimal arithmetic for money and quantities.
 * Values are carried as integers in hundredths (paise / 0.01 units) so totals never drift.
 */
class Money
{
    public static function toMinor($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        $value = trim((string) $value);
        if (!preg_match('/^(-)?(\d*)(?:\.(\d*))?$/', $value, $parts) || ($parts[2] === '' && ($parts[3] ?? '') === '')) {
            throw new InvalidArgumentException('Invalid decimal value: ' . $value);
        }

        $fraction = $parts[3] ?? '';
        $minor = (int) ($parts[2] ?: '0') * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        // Half-up on the third decimal place.
        if (strlen($fraction) > 2 && (int) $fraction[2] >= 5) {
            $minor++;
        }

        return $parts[1] === '-' ? -$minor : $minor;
    }

    public static function fromMinor($minor)
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign . intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Multiply two hundredths-scaled values (e.g. quantity x rate) and return hundredths, rounded half-up.
     */
    public static function multiply($aMinor, $bMinor)
    {
        return self::divideRounded($aMinor * $bMinor, 100);
    }

    /**
     * Apply a percentage (hundredths-scaled, e.g. 1800 = 18.00%) to an amount in minor units.
     */
    public static function percentOf($amountMinor, $percentMinor)
    {
        return self::divideRounded($amountMinor * $percentMinor, 10000);
    }

    private static function divideRounded($numerator, $denominator)
    {
        $sign = $numerator < 0 ? -1 : 1;
        $numerator = abs($numerator);

        return $sign * intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
