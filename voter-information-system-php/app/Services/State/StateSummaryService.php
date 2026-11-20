<?php

namespace App\Services\State;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * State side: collects the feeds of the division installations (STATE_SOURCES) into state_snapshots and adds them up.
 * Pulled by a cron job (php artisan vis:state-pull) or, without cron, when the page is opened and the figures are stale.
 * A division that cannot be reached keeps its last figures and is shown as offline.
 */
class StateSummaryService
{
    /** [['name' => 'Lucknow', 'url' => 'https://…/div-lucknow', 'token' => '…'], …] from STATE_SOURCES="Name|URL|TOKEN;Name|URL|TOKEN". */
    public static function sources(): array
    {
        $out = [];
        foreach (preg_split('/[;\n]+/', (string) config('voters.state.sources', '')) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) === 3 && $p[0] !== '' && preg_match('#^https?://#i', $p[1])) $out[] = ['name' => mb_substr($p[0], 0, 100), 'url' => rtrim($p[1], '/'), 'token' => $p[2]];
        }
        return $out;
    }

    public static function enabled(): bool { return self::sources() !== []; }

    /** Fetches every division at the same time (25 s each at most); returns [name => ok]. */
    public function pull(): array
    {
        $src = self::sources();
        $now = now();
        $res = Http::pool(fn (Pool $pool) => array_map(fn ($s) => $pool->as($s['name'])->withToken($s['token'])->acceptJson()
            ->connectTimeout(8)->timeout(25)->get($s['url'] . '/state-feed'), $src));
        $done = [];
        foreach ($src as $s) {
            $r = $res[$s['name']] ?? null;
            $row = ['url' => $s['url'], 'fetched_at' => $now];
            $ok = false; $err = null;
            if ($r instanceof \Throwable) $err = $r->getMessage();
            elseif (!$r || !$r->successful()) $err = 'HTTP ' . ($r?->status() ?? '?');
            elseif (!is_array($j = $r->json()) || !isset($j['assemblies'])) $err = 'not a state feed';
            else { $ok = true; $row['payload'] = json_encode($j, JSON_UNESCAPED_UNICODE); $row['last_ok_at'] = $now; }
            $row['ok'] = $ok; $row['error'] = $err ? mb_substr($err, 0, 300) : null;
            DB::table('state_snapshots')->updateOrInsert(['source' => $s['name']], $row);
            $done[$s['name']] = $ok;
        }
        DB::table('state_snapshots')->whereNotIn('source', array_column($src, 'name') ?: ['-'])->delete();
        return $done;
    }

    /** Pulls when the newest figures are older than STATE_REFRESH_SECONDS (180); only one request pulls at a time. */
    public function pullIfStale(): void
    {
        $age = max(30, (int) config('voters.state.refresh_seconds', 180));
        $last = DB::table('state_snapshots')->min('fetched_at');
        $count = DB::table('state_snapshots')->count();
        if ($count >= count(self::sources()) && $last && strtotime($last) > time() - $age) return;
        $lock = Cache::lock('vis.state.pull', 120);
        if (!$lock->get()) return;
        try { $this->pull(); } finally { $lock->release(); }
    }

    /** Divisions (with their assemblies) and state totals. */
    public function summary(): array
    {
        $keys = ['voters', 'male', 'female', 'other', 'booths', 'mobile', 'surveyed', 'S', 'N', 'O', 'U', 'workers', 'panna', 'grievances', 'voted', 'supportersVoted'];
        $zero = array_fill_keys($keys, 0);
        $snaps = DB::table('state_snapshots')->get()->keyBy('source');
        $divisions = []; $total = $zero + ['assemblies' => 0]; $election = null;
        foreach (self::sources() as $s) {
            $snap = $snaps[$s['name']] ?? null;
            $feed = $snap && $snap->payload ? json_decode($snap->payload, true) : null;
            $d = $zero + ['name' => $s['name'], 'url' => $s['url'], 'ok' => (bool) ($snap->ok ?? false), 'error' => $snap->error ?? ($snap ? null : 'not fetched yet'),
                'fetchedAt' => $snap->fetched_at ?? null, 'lastOkAt' => $snap->last_ok_at ?? null, 'generatedAt' => $feed['generatedAt'] ?? null,
                'assemblies' => [], 'hasData' => (bool) $feed];
            foreach ($feed['assemblies'] ?? [] as $ac) {
                $ac += $zero;
                $d['assemblies'][] = $ac;
                foreach ($keys as $k) $d[$k] += (int) $ac[$k];
            }
            $election ??= $feed['election'] ?? null;
            foreach ($keys as $k) $total[$k] += $d[$k];
            $total['assemblies'] += count($d['assemblies']);
            $divisions[] = $d;
        }
        return ['divisions' => $divisions, 'total' => $total, 'election' => $election,
            'online' => count(array_filter($divisions, fn ($d) => $d['ok'])), 'count' => count($divisions)];
    }

    public static function pct(int $part, int $whole): string { return $whole > 0 ? number_format(100 * $part / $whole, 1) . '%' : '-'; }
}
