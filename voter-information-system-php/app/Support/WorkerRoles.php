<?php

namespace App\Support;

use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Worker roles, set in Masters > Worker roles.
 *
 * The seven built-in roles (Worker::ROLES) are the base types the organisation pages work with: who reports to whom,
 * gaps, team tasks, login access. They can be renamed (English / Hindi / Punjabi), hidden and reordered.
 * Own roles (e.g. "Shakti Kendra Pramukh", "Social media") each "work like" one built-in role, so they take its place
 * in the chain. Without the table (not upgraded yet) the built-in list is used as before.
 */
class WorkerRoles
{
    private static ?array $rows = null;

    public static function flush(): void { self::$rows = null; }

    /** All roles in display order: code => row (code, base, name, name_hi, name_pa, sort, active, builtin, id). */
    public static function all(): array
    {
        if (self::$rows !== null) return self::$rows;
        $rows = [];
        try {
            if (Schema::hasTable('worker_roles')) foreach (DB::table('worker_roles')->orderBy('sort')->orderBy('id')->get() as $r) {
                if ($r->builtin && !in_array($r->code, Worker::ROLES, true)) continue;
                if (!in_array($r->base, Worker::ROLES, true)) continue;
                $rows[$r->code] = (array) $r;
            }
        } catch (\Throwable) { $rows = []; }
        foreach (Worker::ROLES as $i => $code) {   // built-in roles are always there
            $rows[$code] ??= ['id' => null, 'code' => $code, 'base' => $code, 'name' => null, 'name_hi' => null, 'name_pa' => null, 'sort' => ($i + 1) * 10, 'active' => 1, 'builtin' => 1];
        }
        uasort($rows, fn ($a, $b) => [$a['sort'], $a['id'] ?? 0] <=> [$b['sort'], $b['id'] ?? 0]);
        return self::$rows = $rows;
    }

    /** Role codes in order; $activeOnly leaves out hidden ones ($keep: the role a worker already has stays selectable). */
    public static function codes(bool $activeOnly = false, ?string $keep = null): array
    {
        return array_keys(array_filter(self::all(), fn ($r) => !$activeOnly || $r['active'] || $r['code'] === $keep));
    }

    /** The built-in role a role works like. */
    public static function base(?string $code): string
    {
        return self::all()[$code]['base'] ?? (in_array($code, Worker::ROLES, true) ? $code : 'VOLUNTEER');
    }

    /** Every role that works like this built-in role (itself included): for "where role in (...)". */
    public static function like(string $base): array
    {
        return array_keys(array_filter(self::all(), fn ($r) => $r['base'] === $base));
    }

    /** Quoted list for SQL "role in (...)" (codes are A-Z, 0-9 and _ only). */
    public static function sqlList(string $base): string
    {
        return implode(',', array_map(fn ($c) => "'" . preg_replace('/[^A-Z0-9_]/', '', $c) . "'", self::like($base))) ?: "''";
    }

    /** Role for a new worker: Volunteer, or the last shown role when Volunteer is hidden. */
    public static function defaultCode(): string
    {
        $codes = self::codes(true);
        return in_array('VOLUNTEER', $codes, true) ? 'VOLUNTEER' : (end($codes) ?: 'VOLUNTEER');
    }

    public static function active(string $code): bool { return (bool) (self::all()[$code]['active'] ?? false); }

    /** Name in the screen language: own name > English own name > standard translation. */
    public static function label(?string $code): string
    {
        $r = self::all()[$code] ?? null;
        if (!$r) return (string) $code;
        $lang = Lang::current();
        $own = $lang === 'hi' ? $r['name_hi'] : ($lang === 'pa' ? $r['name_pa'] : null);
        if ($own) return $own;
        if ($r['name']) return $r['name'];
        return $r['builtin'] ? t('worker.role.' . $code) : $code;
    }

    /** Built-in rows in the table (once; safe to run again). */
    public static function seed(): void
    {
        if (!Schema::hasTable('worker_roles')) return;
        foreach (Worker::ROLES as $i => $code) {
            if (DB::table('worker_roles')->where('code', $code)->exists()) continue;
            DB::table('worker_roles')->insert(['code' => $code, 'base' => $code, 'sort' => ($i + 1) * 10, 'active' => true, 'builtin' => true, 'created_at' => now()]);
        }
        self::flush();
    }

    /** A code for a new own role, from its English name: SOCIAL_MEDIA, SOCIAL_MEDIA_2 ... (max 30). */
    public static function newCode(string $name): string
    {
        $base = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_')) ?: 'ROLE';
        if (ctype_digit($base[0])) $base = 'R_' . $base;
        $base = substr($base, 0, 26);
        $code = $base; $n = 2;
        while (isset(self::all()[$code]) || DB::table('worker_roles')->where('code', $code)->exists()) $code = $base . '_' . $n++;
        return $code;
    }
}
