<?php

namespace App\Support;

use App\Models\AppUser;
use App\Services\Auth\CurrentUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Access area of the logged-in user inside their assembly: ward(s), booth(s) and a serial range of one booth.
 * Every voter query is limited to it (Voter global scope + Area::voters() for report queries), so lists,
 * exports, reports, analytics, dashboard, field survey, poll day and grievances only show the user's area.
 */
class Area
{
    /** ['wards' => int[], 'booths' => int[], 'from' => ?int, 'to' => ?int] or null when the user has no area (whole assembly). */
    public static function current(): ?array
    {
        if (!app()->bound('request') || !app()->bound('session')) return null;
        $req = request();
        if ($req->attributes->has('vis.area')) return $req->attributes->get('vis.area');
        $area = null;
        $user = app(CurrentUser::class);
        if ($user->check() && config('voters.security.user_source') !== 'external' && Schema::hasColumn('app_users', 'scope_booths')) {
            $u = AppUser::where('username', $user->username())->first(['scope_wards', 'scope_booths', 'scope_serial_from', 'scope_serial_to']);
            if ($u) $area = self::of($u);
        }
        $req->attributes->set('vis.area', $area);
        return $area;
    }

    /** Area stored on a user record (null = none). */
    public static function of(AppUser $u): ?array
    {
        $a = ['wards' => self::ints($u->scope_wards), 'booths' => self::ints($u->scope_booths),
            'from' => $u->scope_serial_from !== null ? (int) $u->scope_serial_from : null, 'to' => $u->scope_serial_to !== null ? (int) $u->scope_serial_to : null];
        return $a['wards'] || $a['booths'] || $a['from'] !== null || $a['to'] !== null ? $a : null;
    }

    /** "101, 102" -> [101, 102] */
    public static function ints(?string $s): array
    {
        return array_values(array_unique(array_map('intval', array_filter(preg_split('/[\s,;]+/', (string) $s), fn ($x) => ctype_digit($x) && (int) $x > 0))));
    }

    /** Adds the area conditions to a voters query; $p = column prefix ("voters." or "v."). */
    public static function apply($q, string $p = '', ?array $area = null)
    {
        $a = $area ?? self::current();
        if (!$a) return $q;
        if ($a['wards']) $q->whereIn($p . 'ward_no', $a['wards']);
        if ($a['booths']) $q->whereIn($p . 'booth_no', $a['booths']);
        if ($a['from'] !== null) $q->where($p . 'serial_no', '>=', $a['from']);
        if ($a['to'] !== null) $q->where($p . 'serial_no', '<=', $a['to']);
        return $q;
    }

    /** DB::table('voters') limited to the area (for report queries that do not use the Voter model). */
    public static function voters() { return self::apply(DB::table('voters'), 'voters.'); }

    /** Is this voter / these voter fields inside the area? */
    public static function fits($v, ?array $area = null): bool
    {
        $a = $area ?? self::current();
        if (!$a) return true;
        $get = fn ($k) => is_array($v) ? ($v[$k] ?? null) : $v->$k;
        if ($a['wards'] && !in_array((int) $get('ward_no'), $a['wards'], true)) return false;
        if ($a['booths'] && !in_array((int) $get('booth_no'), $a['booths'], true)) return false;
        $s = $get('serial_no');
        if ($a['from'] !== null && ($s === null || (int) $s < $a['from'])) return false;
        if ($a['to'] !== null && ($s === null || (int) $s > $a['to'])) return false;
        return true;
    }

    /** Short label for the header badge and the users list: "Ward 102 · Booth 3 · Sr. 31-60". */
    public static function label(?array $a): string
    {
        if (!$a) return '';
        $p = [];
        if ($a['wards']) $p[] = t('col.wardNo') . ' ' . implode(', ', $a['wards']);
        if ($a['booths']) $p[] = t('col.boothNo') . ' ' . implode(', ', $a['booths']);
        if ($a['from'] !== null || $a['to'] !== null) $p[] = t('area.serials', $a['from'] ?? 1, $a['to'] ?? '…');
        return implode(' · ', $p);
    }
}
