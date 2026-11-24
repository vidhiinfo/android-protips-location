<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'json'];

    private const CACHE_KEY = 'office.settings';

    /** All settings merged over the defaults in config/office.php ("defaults" key). */
    public static function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = Schema::hasTable('settings') ? static::query()->pluck('value', 'key')->all() : [];

            return array_replace(config('office.defaults', []), $stored);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allValues();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        // Support dotted access into array settings, e.g. "permissions.state_admin.members".
        return Arr::get($all, $key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
