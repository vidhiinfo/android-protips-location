<?php

use App\Support\Lang;

if (!function_exists('t')) {
    /** Translate a message key into the current language: t('nav.voters'), t('list.showing', 1, 25, 300). */
    function t(string $key, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        return Lang::get($key, $args);
    }
}

if (!function_exists('can')) {
    /** Does the logged-in user have this permission? */
    function can(string $permission): bool
    {
        return app(\App\Services\Auth\CurrentUser::class)->can($permission);
    }
}

if (!function_exists('canAny')) {
    function canAny(string ...$permissions): bool
    {
        foreach ($permissions as $p) if (can($p)) return true;
        return false;
    }
}

if (!function_exists('versioned_asset')) {
    /** asset() + ?v=<file time>, so browsers load the new CSS / JS right after an update instead of a cached copy. */
    function versioned_asset(string $path): string
    {
        $file = public_path($path);
        return asset($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}
