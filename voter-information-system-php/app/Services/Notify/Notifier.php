<?php

namespace App\Services\Notify;

use App\Models\Worker;
use App\Support\WorkerRoles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications: the bell in the top bar (website and phone app). Each notification is one row per login.
 * Sent by hand (Announcements: to everyone, a login role, a worker role, an assembly / mandal / booth) and
 * automatically (task given, grievance assigned, grievance escalated). Kept 90 days.
 */
class Notifier
{
    public const KINDS = ['ANNOUNCE', 'TASK', 'GRIEVANCE', 'ESCALATION'];
    private const KEEP_DAYS = 90;

    public static function enabled(): bool
    {
        static $on = null;
        try { return $on ??= Schema::hasTable('notifications'); } catch (\Throwable) { return false; }
    }

    /** Send to these logins (unknown / empty names are skipped). Returns how many got it. */
    public function send(iterable $usernames, string $kind, string $title, ?string $body = null, ?string $url = null, ?string $by = null): int
    {
        if (!self::enabled()) return 0;
        $names = collect($usernames)->filter()->map(fn ($u) => (string) $u)->unique()->values();
        if ($names->isEmpty()) return 0;
        $names = DB::table('app_users')->whereIn('username', $names->all())->where('enabled', true)->pluck('username');
        $now = now(); $rows = [];
        foreach ($names as $u) $rows[] = ['username' => $u, 'kind' => $kind, 'title' => mb_substr($title, 0, 200), 'body' => $body !== null ? mb_substr($body, 0, 1000) : null,
            'url' => $url !== null ? mb_substr($url, 0, 300) : null, 'created_by' => $by, 'created_at' => $now];
        foreach (array_chunk($rows, 500) as $chunk) DB::table('notifications')->insert($chunk);
        if (random_int(1, 50) === 1) DB::table('notifications')->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        return count($rows);
    }

    /** Notify the login linked to a worker (if the worker has one). */
    public function toWorker(?int $workerId, string $kind, string $title, ?string $body = null, ?string $url = null, ?string $by = null): int
    {
        $u = $workerId ? Worker::where('id', $workerId)->value('username') : null;
        return $u && $u !== $by ? $this->send([$u], $kind, $title, $body, $url, $by) : 0;
    }

    public function unread(string $username): int
    {
        if (!self::enabled()) return 0;
        return DB::table('notifications')->where('username', $username)->whereNull('read_at')->count();
    }

    public function latest(string $username, int $limit = 8): Collection
    {
        if (!self::enabled()) return collect();
        return DB::table('notifications')->where('username', $username)->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * Logins an announcement goes to. $t: target = all | loginrole | workerrole; role code; assembly; mandal_id; booth.
     * $limitAssembly: a sender limited to one assembly reaches only that assembly; $limitBooths: only those booths.
     */
    public function recipients(array $t, ?int $limitAssembly, array $limitBooths = []): Collection
    {
        $a = $limitAssembly ?: (!empty($t['assembly']) ? (int) $t['assembly'] : null);
        $booth = !empty($t['booth']) ? (int) $t['booth'] : null;
        if ($limitBooths && $booth && !in_array($booth, $limitBooths, true)) return collect();
        $users = DB::table('app_users')->where('enabled', true);
        if (($t['target'] ?? 'all') === 'loginrole' && !empty($t['role'])) {
            $users->whereIn('id', DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('roles.code', $t['role'])->select('user_roles.user_id'));
        }
        $viaWorkers = ($t['target'] ?? 'all') === 'workerrole' || $booth || !empty($t['mandal']) || $limitBooths;
        if ($viaWorkers) {
            // through the organisation: logins linked to workers of that role / place
            $w = Worker::where('active', true)->whereNotNull('username');
            if (($t['target'] ?? '') === 'workerrole' && !empty($t['role'])) $w->where('role', $t['role']);
            if ($a) $w->whereIn('assembly_no', [$a, Worker::ALL_ASSEMBLIES]);
            if ($booth) $w->where('booth_no', $booth);
            elseif ($limitBooths) $w->whereIn('booth_no', $limitBooths);
            if (!empty($t['mandal']) && ($m = \App\Models\Mandal::find((int) $t['mandal']))) {
                $mb = $m->boothList();
                $w->where(fn ($q) => $q->where('mandal_id', $m->id)->orWhereIn('booth_no', $mb ?: [0]));
            }
            $users->whereIn('username', $w->pluck('username')->all() ?: ['']);
        } elseif ($a) {
            // logins of that assembly (or of all assemblies, e.g. Lok Sabha team)
            $users->where(fn ($q) => $q->where('assembly_no', $a)->orWhereNull('assembly_no'));
        }
        return $users->pluck('username');
    }
}
