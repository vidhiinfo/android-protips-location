<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Settings the administrator changes on screen (Admin > Settings). They take priority over .env. */
class Settings
{
    private const CACHE = 'vis.app_settings';

    /**
     * Everything an administrator can change on screen: name => [group, config key, type, rule].
     * type: int (rule = [min, max]), bool, enum (rule = choices), text (rule = max length).
     * Secrets (database, API keys, webhook token) stay in .env on purpose.
     */
    public const SPEC = [
        'messaging_enabled'       => ['messaging', 'voters.messaging.enabled', 'bool', null],
        'task_reminder_channel'   => ['messaging', 'voters.tasks.reminder_channel', 'enum', ['none', 'sms', 'whatsapp']],
        'grievance_alert_channel' => ['messaging', 'voters.grievance.alert_channel', 'enum', ['none', 'sms', 'whatsapp']],
        'offline_keep_days'       => ['offline', 'voters.survey.offline_days', 'int', [1, 30]],
        'field_max_booths'        => ['offline', 'voters.survey.field_max_booths', 'int', [1, 20]],
        'field_session_days'      => ['login', 'voters.security.field_session_days', 'int', [1, 30]],
        'office_session_minutes'  => ['login', 'voters.security.office_session_minutes', 'int', [15, 1440]],
        'otp_required'            => ['login', 'voters.security.otp_required', 'enum', ['none', 'admins', 'all']],
        'show_ward_no'            => ['display', 'voters.show_ward_no', 'bool', null],
        'app_name'                => ['display', 'voters.branding.app_name', 'text', 100],
        'app_name_local'          => ['display', 'voters.branding.app_name_local', 'text', 100],
        'short_name'              => ['display', 'voters.branding.short_name', 'text', 20],
        'owner'                   => ['display', 'voters.branding.owner', 'text', 100],
    ];

    /** The value from .env / config files, before any screen setting (shown as the default on the page). */
    private static array $defaults = [];

    public static function envDefault(string $name)
    {
        return array_key_exists($name, self::$defaults) ? self::$defaults[$name] : config(self::SPEC[$name][1]);
    }

    /** Runs at start-up: screen settings override .env. */
    public static function applyToConfig(): void
    {
        foreach (self::SPEC as $name => [$group, $key]) self::$defaults[$name] ??= config($key);
        foreach (self::all() as $name => $raw) {
            if (!isset(self::SPEC[$name]) || $raw === null) continue;
            [, $key, $type] = self::SPEC[$name];
            config([$key => match ($type) { 'int' => (int) $raw, 'bool' => $raw === '1', default => (string) $raw }]);
        }
    }
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
        if (isset(self::SPEC[$name])) {
            self::$defaults[$name] ??= config(self::SPEC[$name][1]);
            [, $key, $type] = self::SPEC[$name];
            config([$key => match ($type) { 'int' => (int) $value, 'bool' => $value === '1', default => (string) $value }]);
        }
    }

    public static function flush(): void { Cache::forget(self::CACHE); app()->forgetInstance('vis.settings'); }

    /** Back to the .env value for one setting. */
    public static function forget(string $name): void
    {
        DB::table('app_settings')->where('name', $name)->delete();
        self::flush();
        if (isset(self::SPEC[$name])) config([self::SPEC[$name][1] => self::envDefault($name)]);
    }
}
