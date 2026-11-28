<?php

use App\Models\Lookup;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(mixed $d): string
    {
        if (! $d) {
            return '';
        }
        $d = $d instanceof CarbonInterface ? $d : Carbon::parse($d);

        return $d->format(setting('date_format', 'd M Y'));
    }
}

if (! function_exists('fmt_datetime')) {
    function fmt_datetime(mixed $d): string
    {
        if (! $d) {
            return '';
        }
        $d = $d instanceof CarbonInterface ? $d : Carbon::parse($d);

        return $d->format(setting('datetime_format', 'd M Y, h:i A'));
    }
}

if (! function_exists('lookup_label')) {
    function lookup_label(string $group, ?string $value): string
    {
        return Lookup::label($group, $value);
    }
}

if (! function_exists('render_template')) {
    /** Replace {placeholders} in an admin-configured message template. */
    function render_template(string $template, array $vars): string
    {
        $vars += ['signature' => setting('greeting_signature'), 'org' => setting('org_name')];

        return strtr($template, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }
}

if (! function_exists('leader_designations')) {
    /** Leader's designation lines (one per line in Settings). */
    function leader_designations(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) setting('leader_designation', '')))));
    }
}

if (! function_exists('branding_image_url')) {
    /** Public URL of an uploaded branding image ("logo" or "leader"), or null. Works without `php artisan storage:link`. */
    function branding_image_url(string $type): ?string
    {
        $path = setting($type === 'leader' ? 'leader_photo_path' : 'logo_path');

        return $path ? route('branding.image', $type).'?v='.substr(md5((string) $path), 0, 8) : null;
    }
}

if (! function_exists('wa_link')) {
    /** wa.me click-to-chat link; assumes Indian numbers when no country code is given. */
    function wa_link(?string $mobile, string $text = ''): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $mobile);
        if (strlen($digits) < 10) {
            return null;
        }
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return 'https://wa.me/'.$digits.($text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
