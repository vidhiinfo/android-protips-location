<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Cache for figures that read a whole assembly (dashboard, drop-down lists, totals), safe under many users:
 *  - only ONE request rebuilds a value; the others do not start the same heavy query at the same time;
 *  - a stale value (older than $ttl) is still served while that one request rebuilds it;
 *  - only when there is no value at all do the others wait (up to 45 s) for the one rebuild.
 */
class SharedCache
{
    public static function remember(string $key, int $ttl, \Closure $make)
    {
        $hit = Cache::get($key);
        if (is_array($hit) && array_key_exists('v', $hit)) {
            if ($hit['t'] > time() - $ttl) return $hit['v'];
            $lock = Cache::lock($key . ':lock', 120);
            if (!$lock->get()) return $hit['v'];   // someone else is refreshing it: use the previous figures meanwhile
            try { return self::store($key, $ttl, $make); } finally { $lock->release(); }
        }
        try {
            return Cache::lock($key . ':lock', 120)->block(45, fn () => (($h = Cache::get($key)) && is_array($h) && array_key_exists('v', $h)) ? $h['v'] : self::store($key, $ttl, $make));
        } catch (LockTimeoutException $e) {
            return $make();
        }
    }

    private static function store(string $key, int $ttl, \Closure $make)
    {
        $v = $make();
        Cache::put($key, ['v' => $v, 't' => time()], $ttl * 12);   // kept longer than $ttl so a stale copy can be served
        return $v;
    }
}
