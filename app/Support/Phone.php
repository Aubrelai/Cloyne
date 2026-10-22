<?php

namespace App\Support;

/** US phone numbers: one stored format (+13055550100), one display format ((305) 555-0100). */
class Phone
{
    /** "+1 305-555-0100", "(305) 555 0100" → "+13055550100". Non-US or odd numbers are kept as typed. */
    public static function normalize(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? '+1'.$digits : trim($phone);
    }

    public static function display(?string $phone): ?string
    {
        $e164 = self::normalize($phone);
        if ($e164 && preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $m)) {
            return "({$m[1]}) {$m[2]}-{$m[3]}";
        }

        return $e164;
    }

    /** For tel: links. */
    public static function dial(?string $phone): ?string
    {
        $e164 = self::normalize($phone);

        return $e164 ? preg_replace('/[^\d+]/', '', $e164) : null;
    }
}
