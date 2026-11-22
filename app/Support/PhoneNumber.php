<?php

namespace App\Support;

/**
 * Nigerian phone numbers in E.164 (+234XXXXXXXXXX).
 *
 * Accepts the shapes people actually type: 0803 123 4567, 803-123-4567,
 * 2348031234567, +234 803 123 4567.
 */
final class PhoneNumber
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';

        $national = match (true) {
            str_starts_with($digits, '234') && strlen($digits) === 13 => substr($digits, 3),
            str_starts_with($digits, '0') && strlen($digits) === 11 => substr($digits, 1),
            strlen($digits) === 10 => $digits,
            default => null,
        };

        // Nigerian mobile and landline national numbers start 7, 8 or 9 (mobile) or 1-9 (fixed).
        if ($national === null || ! preg_match('/^[1-9]\d{9}$/', $national)) {
            return null;
        }

        return '+234'.$national;
    }

    /**
     * Display form: +234 803 123 4567.
     */
    public static function format(?string $e164): string
    {
        if (! $e164 || ! preg_match('/^\+234(\d{3})(\d{3})(\d{4})$/', $e164, $m)) {
            return (string) $e164;
        }

        return "+234 {$m[1]} {$m[2]} {$m[3]}";
    }

    /**
     * Masked form for "we sent a code to ..." messages: +234 803 *** 4567.
     */
    public static function mask(?string $e164): string
    {
        if (! $e164 || ! preg_match('/^\+234(\d{3})\d{3}(\d{4})$/', $e164, $m)) {
            return (string) $e164;
        }

        return "+234 {$m[1]} *** {$m[2]}";
    }
}
