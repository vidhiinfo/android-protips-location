<?php

namespace App\Support;

class Text
{
    /** Trim and Unicode-NFC normalise (same Hindi / Urdu text typed on different keyboards then matches). */
    public static function nfc(?string $s): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        if ($s === '') return null;
        if (class_exists(\Normalizer::class)) $s = \Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s;
        return $s;
    }

    /** Native digits (०-९, ০-৯, ۰-۹, ௦-௯ ...) to 0-9. */
    public static function asciiDigits(?string $s): ?string
    {
        if ($s === null) return null;
        return preg_replace_callback('/\p{Nd}/u', function ($m) {
            $cp = mb_ord($m[0]);
            if ($cp < 128) return $m[0];
            // every Unicode decimal digit block is a run of 10 starting at ...0
            foreach ([0x0660, 0x06F0, 0x07C0, 0x0966, 0x09E6, 0x0A66, 0x0AE6, 0x0B66, 0x0BE6, 0x0C66, 0x0CE6, 0x0D66, 0x0DE6, 0x0E50, 0x0ED0, 0x0F20, 0x1040, 0xFF10] as $zero) {
                if ($cp >= $zero && $cp < $zero + 10) return (string) ($cp - $zero);
            }
            return $m[0];
        }, $s);
    }

    /** Letters and digits only, lower case, NFC - key for duplicate matching. */
    /**
     * Household matching key for a house number: lower case, without spaces, hyphens and dots,
     * so "RZ-154", "R Z-154" and "rz154" are one house; "RZ-174/FF" stays different from "RZ-174".
     */
    public static function houseKey(?string $s): string
    {
        return str_replace([' ', '-', '.'], '', mb_strtolower(trim((string) self::asciiDigits((string) $s))));
    }

    /** The same key computed by the database (MySQL, MariaDB and SQLite). */
    public const HOUSE_KEY_SQL = "lower(replace(replace(replace(trim(coalesce(house_no, '')), ' ', ''), '-', ''), '.', ''))";

    public static function nameKey(?string $s): string
    {
        $s = mb_strtolower((string) self::nfc($s));
        return preg_replace('/[^\p{L}\p{N}\p{M}]/u', '', $s);
    }

    public static function lower(?string $s): string
    {
        return mb_strtolower((string) self::nfc($s));
    }

    /** Short "Browser on OS" label from a user-agent string, for the audit log. */
    public static function device(?string $ua): string
    {
        if (!$ua) return '';
        $b = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet', str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => '',
        };
        $o = match (true) {
            str_contains($ua, 'Android') => 'Android', str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Linux') => 'Linux', default => '',
        };
        return trim($b . ($b && $o ? ' / ' : '') . $o) ?: mb_substr($ua, 0, 30);
    }
}
