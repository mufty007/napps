<?php

namespace App\Support;

/**
 * Naira amounts. Money is stored as integer kobo everywhere (Paystack's unit
 * too), and only turned into naira for display and form input.
 */
final class Money
{
    /**
     * ₦25,000 (or ₦25,000.50 when there are kobo).
     */
    public static function format(int|float|null $kobo): string
    {
        $kobo = (int) round((float) $kobo);
        $negative = $kobo < 0;
        $kobo = abs($kobo);
        $decimals = $kobo % 100 === 0 ? 0 : 2;

        return ($negative ? '-' : '').'₦'.number_format($kobo / 100, $decimals);
    }

    /**
     * Parse what people type ("25000", "25,000.50", "₦ 25 000") into kobo.
     * Returns null for anything that is not a non-negative amount.
     */
    public static function toKobo(string|int|float|null $naira): ?int
    {
        if ($naira === null || $naira === '') {
            return null;
        }

        $clean = preg_replace('/[\s,₦]+/u', '', (string) $naira) ?? '';

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $clean), 2, '0');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * Kobo as a plain naira string for form fields ("25000" or "25000.50").
     */
    public static function toNaira(?int $kobo): string
    {
        if ($kobo === null) {
            return '';
        }

        return $kobo % 100 === 0 ? (string) intdiv($kobo, 100) : number_format($kobo / 100, 2, '.', '');
    }
}
