<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Database backup written by PHP itself (no mysqldump / shell needed, so it works on shared hosting):
 * storage/app/backups/db-YYYY-MM-DD_HHMMSS.sql.gz, the newest BACKUP_KEEP files kept.
 * The file can be imported in phpMyAdmin (Import accepts .sql.gz) into an empty database.
 * Runs from a daily cron job (php artisan vis:backup) and, as a fallback, once a day after a page visit.
 */
class BackupService
{
    private const ROWS = 500;

    public static function dir(): string
    {
        $d = storage_path('app/backups');
        if (!is_dir($d)) @mkdir($d, 0755, true);
        if (!is_file("$d/.htaccess")) @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n");
        if (!is_file("$d/index.html")) @file_put_contents("$d/index.html", '');
        return $d;
    }

    /** Backups, newest first: [name, path, size, time]. */
    public function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/db-*.sql.gz') ?: [] as $f) $out[] = ['name' => basename($f), 'path' => $f, 'size' => filesize($f), 'time' => filemtime($f)];
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /** True when the newest backup is older than BACKUP_EVERY_HOURS (24) or there is none. */
    public function due(): bool
    {
        $l = $this->list();
        return !$l || $l[0]['time'] < time() - 3600 * max(1, (int) config('voters.backup.every_hours', 24));
    }

    /** Called after each response (see bootstrap/app.php): at most one check an hour, one backup a day. */
    public function runIfDue(): void
    {
        if (!config('voters.backup.on_visit', true) || Cache::get('vis.backup.checked')) return;
        Cache::put('vis.backup.checked', true, 3600);
        if ($this->due()) { try { $this->run(); } catch (\Throwable $e) { report($e); } }
    }

    /** Writes a new backup; returns its file name, or null when another backup is running. */
    public function run(): ?string
    {
        $lock = Cache::lock('vis.backup.running', 3600);
        if (!$lock->get()) return null;
        try {
            @set_time_limit(0);
            @ignore_user_abort(true);
            $dir = self::dir();
            $name = 'db-' . date('Y-m-d_His') . '.sql.gz';
            $tmp = "$dir/.$name.part";
            $gz = gzopen($tmp, 'wb6');
            if (!$gz) throw new \RuntimeException("Cannot write $tmp");
            $db = DB::connection();
            $driver = $db->getDriverName();
            $pdo = $db->getPdo();
            $w = fn (string $s) => gzwrite($gz, $s);
            $w("-- Voter Information System database backup\n-- " . date('Y-m-d H:i:s') . ' ' . config('app.url') . "\n"
                . "-- Restore: phpMyAdmin > an EMPTY database > Import > this .sql.gz file > Go.\n-- Personal data of electors: keep this file private.\n\n");
            if ($driver === 'mysql') $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
            foreach ($this->tables($driver) as $t) {
                $q = $driver === 'mysql' ? "`$t`" : "\"$t\"";
                $create = $driver === 'mysql' ? (array) DB::selectOne("SHOW CREATE TABLE $q") : ['sql' => DB::selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$t])->sql];
                $w("DROP TABLE IF EXISTS $q;\n" . end($create) . ";\n\n");
                $cols = null; $buf = []; $n = 0;
                $flush = function () use (&$buf, &$cols, $w, $q) {
                    if ($buf) $w("INSERT INTO $q (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $buf) . ";\n");
                    $buf = [];
                };
                $hasId = in_array('id', $this->columns($driver, $t), true);
                $skip = array_flip($this->generated($driver, $t));   // computed columns (house_key) are rebuilt by the database
                $last = 0; $offset = 0;
                while (true) {
                    // big tables are read in slices of 500 rows by id (fast at any size); tables without id by offset
                    $rows = $hasId ? DB::table($t)->where('id', '>', $last)->orderBy('id')->limit(self::ROWS)->get()
                                   : DB::table($t)->offset($offset)->limit(self::ROWS)->get();
                    if ($rows->isEmpty()) break;
                    foreach ($rows as $r) {
                        $r = array_diff_key((array) $r, $skip);
                        $cols ??= array_map(fn ($c) => $driver === 'mysql' ? "`$c`" : "\"$c\"", array_keys($r));
                        $buf[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $r)) . ')';
                        if (++$n % 200 === 0) $flush();
                        if ($hasId) $last = $r['id'];
                    }
                    $offset += self::ROWS;
                }
                $flush();
                $w("\n");
            }
            if ($driver === 'mysql') $w("SET FOREIGN_KEY_CHECKS = 1;\n");
            $w("-- end of backup\n");
            gzclose($gz);
            rename($tmp, "$dir/$name");
            $this->prune();
            return $name;
        } finally {
            if (isset($gz) && is_resource($gz)) @gzclose($gz);
            if (isset($tmp) && is_file($tmp)) @unlink($tmp);
            $lock->release();
        }
    }

    private function tables(string $driver): array
    {
        if ($driver === 'mysql') return array_map(fn ($r) => array_values((array) $r)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
        return array_map(fn ($r) => $r->name, DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"));
    }

    private function columns(string $driver, string $t): array
    {
        return \Illuminate\Support\Facades\Schema::getColumnListing($t);
    }

    /** Columns computed by the database (cannot be inserted). */
    private function generated(string $driver, string $t): array
    {
        if ($driver === 'mysql') {
            return array_map(fn ($r) => $r->c, DB::select("select column_name as c from information_schema.columns where table_schema = database() and table_name = ?
                and (extra like '%GENERATED%' or extra like '%VIRTUAL%' or extra like '%PERSISTENT%' or extra like '%STORED%')", [$t]));
        }
        return array_map(fn ($r) => $r->name, array_filter(DB::select("pragma table_xinfo(\"$t\")"), fn ($r) => in_array((int) $r->hidden, [2, 3], true)));
    }

    /** Keeps the newest BACKUP_KEEP (14) backups. */
    private function prune(): void
    {
        foreach (array_slice($this->list(), max(1, (int) config('voters.backup.keep', 14))) as $old) @unlink($old['path']);
    }
}
