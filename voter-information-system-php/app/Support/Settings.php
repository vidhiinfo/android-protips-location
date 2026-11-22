<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Settings the administrator changes on screen (Admin > Settings). They take priority over .env. */
class Settings
{
    private const CACHE = 'vis.app_settings';
    public static function all(): array
    {
        // kept for the current request only (one cache read per page)
        if (app()->bound('vis.settings')) return app('vis.settings');
        try {
            $all = Cache::rememberForever(self::CACHE, fn () => Schema::hasTable('app_settings')
                ? DB::table('app_settings')->pluck('value', 'name')->all() : []);
        } catch (\Throwable) {
            $all = [];
        }
        app()->instance('vis.settings', $all);
        return $all;
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        return array_key_exists($name, self::all()) ? self::all()[$name] : $default;
    }

    public static function set(string $name, ?string $value, ?string $by): void
    {
        DB::table('app_settings')->updateOrInsert(['name' => $name], ['value' => $value, 'updated_by' => $by, 'updated_at' => now()]);
        self::flush();
    }

    public static function flush(): void { Cache::forget(self::CACHE); app()->forgetInstance('vis.settings'); }
}
