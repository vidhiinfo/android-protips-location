<?php

namespace App\Support;

/**
 * Translations from lang/messages/{code}.json (the same keys as the Java version).
 * Placeholders: {0}, {1} ... for arguments, {max} etc. for named values.
 */
class Lang
{
    private static array $cache = [];
    private static ?string $current = null;

    public static function languages(): array
    {
        return config('voters.languages');
    }

    public static function supported(?string $code): string
    {
        $code = strtolower(substr((string) $code, 0, 2));
        return array_key_exists($code, self::languages()) ? $code : 'en';
    }

    public static function current(): string
    {
        return self::$current ?? 'en';
    }

    public static function setCurrent(string $code): void
    {
        self::$current = self::supported($code);
        app()->setLocale(self::$current);
    }

    public static function rtl(?string $code = null): bool
    {
        return (bool) (self::languages()[$code ?? self::current()][2] ?? false);
    }

    public static function messages(string $code): array
    {
        if (!isset(self::$cache[$code])) {
            $file = base_path("lang/messages/{$code}.json");
            self::$cache[$code] = is_file($file) ? json_decode(file_get_contents($file), true) : [];
        }
        return self::$cache[$code];
    }

    /** Product name set in .env (BRAND_APP_NAME / BRAND_APP_NAME_LOCAL), e.g. for a separate state installation. */
    public static function brandName(string $code): ?string
    {
        if (!function_exists('config') || !app()->bound('config')) return null;
        $local = (string) config('voters.branding.app_name_local');
        $name = (string) config('voters.branding.app_name');
        if ($code !== 'en' && $local !== '') return $local;
        return $name !== '' ? $name : null;
    }

    public static function get(string $key, array $args = [], ?string $code = null): string
    {
        $code ??= self::current();
        if ($key === 'app.name' && ($own = self::brandName($code)) !== null) return $own;
        $text = self::messages($code)[$key] ?? self::messages('en')[$key] ?? $key;
        foreach (array_values($args) as $i => $v) {
            $text = str_replace('{' . $i . '}', (string) $v, $text);
        }
        foreach ($args as $k => $v) {
            if (is_string($k)) $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
        return $text;
    }
}
