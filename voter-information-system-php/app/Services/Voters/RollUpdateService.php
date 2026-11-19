<?php

namespace App\Services\Voters;

use App\Models\RollUpdate;
use App\Services\Export\ExcelImporter;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * Roll update: brings a new voter roll (final roll, revised draft, supplementary list) into an assembly that is already
 * in the system, without losing anything the party has entered.
 *
 *  1. stage()  reads the new roll and compares it with the voters already there (nothing is changed yet):
 *       SAME      EPIC found, same booth and same name / relative / gender / house (age or serial may change)
 *       CHANGED   EPIC found, name, relative, gender, house no. or address corrected
 *       SHIFTED   EPIC found in another booth (or assembly)
 *       RESTORED  EPIC of a voter marked "deleted" earlier: back on the roll
 *       NAMEMATCH no EPIC match, but exactly one unmatched voter with the same name, relative and gender (EPIC changed / corrected)
 *       NEW       not in the system: added
 *       DELETED   in the system (in a booth that is in the new roll) but not in the new roll: marked "deleted", never removed
 *  2. apply()  writes the roll values only. Mobile, caste, statuses, beneficiaries, survey notes, DOB ... stay as they are.
 *              Voters shifted to another booth lose their panna pramukh (listed for re-assignment). Can be run again if it stops.
 *  3. undo()   puts back the values saved by apply() and removes the voters it added.
 */
class RollUpdateService
{
    /** Values taken from the roll (never the party's own data). Empty values in the new roll keep the current value. */
    public const ROLL_FIELDS = ['assembly_no', 'assembly_name', 'booth_no', 'booth_name', 'serial_no', 'epic_no', 'name', 'name_local', 'relation_type',
        'relative_name', 'relative_name_local', 'gender', 'age', 'house_no', 'address', 'address_local', 'village_ward', 'ward_no', 'district', 'pincode'];
    /** A difference here makes the voter CHANGED (age, serial no. and the booth name change on every roll and do not count). */
    public const IDENTITY_FIELDS = ['name', 'name_local', 'relation_type', 'relative_name', 'relative_name_local', 'gender', 'house_no', 'address'];
    /** Data entered by the party: a voter with any of these "has survey data" (kept by the roll update). */
    public const SURVEY_FIELDS = ['mobile', 'alt_mobile', 'email', 'caste', 'sub_caste', 'social_status', 'political_status', 'organisational_status',
        'beneficiary1', 'beneficiary2', 'beneficiary3', 'membership_no', 'dob', 'anniversary', 'place_of_origin', 'current_place', 'linked_with', 'remarks', 'worker_id'];
    public const ACTIONS = ['NEW', 'DELETED', 'SHIFTED', 'CHANGED', 'NAMEMATCH', 'RESTORED', 'SAME'];
    private const TEXT = ['assembly_name', 'booth_name', 'name', 'name_local', 'relative_name', 'relative_name_local', 'house_no', 'address',
        'address_local', 'village_ward', 'district', 'pincode'];
    private const CHUNK = 500;

    public function __construct(private ExcelImporter $reader) {}

    private static function voters() { return DB::table('voters'); }   // the roll update always works on the whole assembly (no access area)

    private static function hasSurvey(object $v): bool
    {
        foreach (self::SURVEY_FIELDS as $f) if (($v->$f ?? null) !== null && ($v->$f ?? '') !== '') return true;
        return false;
    }

    /** Roll values of an uploaded row, cleaned the same way as a saved voter; empty values left out. */
    private static function rollData(array $d): array
    {
        $out = [];
        foreach (self::ROLL_FIELDS as $f) {
            if (!array_key_exists($f, $d) || $d[$f] === null || $d[$f] === '') continue;
            $v = $d[$f];
            if ($f === 'epic_no') $v = strtoupper((string) Text::asciiDigits(trim((string) $v)));
            elseif (in_array($f, self::TEXT, true)) $v = Text::nfc(trim((string) $v));
            if ($v === null || $v === '') continue;
            $out[$f] = $v;
        }
        return $out;
    }

    private static function same($old, $new, string $f): bool
    {
        if (in_array($f, ['assembly_no', 'booth_no', 'serial_no', 'age', 'ward_no'], true)) return $old !== null && (int) $old === (int) $new;
        return Text::nfc(trim((string) $old)) === (string) $new;
    }

    /** field => [old, new] for the roll values that differ. */
    private static function diff(object $voter, array $data): array
    {
        $c = [];
        foreach ($data as $f => $new) if (!self::same($voter->$f ?? null, $new, $f)) $c[$f] = [$voter->$f ?? null, $new];
        return $c;
    }

    private static function classify(object $voter, array $changes): string
    {
        if ($voter->deleted) return 'RESTORED';
        if (isset($changes['booth_no']) || isset($changes['assembly_no'])) return 'SHIFTED';
        return array_intersect_key($changes, array_flip(self::IDENTITY_FIELDS)) ? 'CHANGED' : 'SAME';
    }

    private static function nameKey($name, $relative, $gender): string
    {
        return Text::nameKey($name) . '|' . Text::nameKey($relative) . '|' . $gender;
    }

    private static function json($v): ?string { return $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE); }

    // ------------------------------------------------------------------ 1. compare

    /** Reads the new roll of one assembly and records what would change. Nothing in the voter list is changed. */
    public function stage(string $path, string $fileName, int $assembly, ?string $user): RollUpdate
    {
        @set_time_limit(0);
        $u = RollUpdate::create(['assembly_no' => $assembly, 'file_name' => mb_substr($fileName, 0, 255), 'status' => 'PREVIEW',
            'created_by' => $user, 'created_at' => now()]);
        $errors = []; $buf = []; $booths = [];
        $flush = function () use (&$buf, &$booths, &$errors, $u) {
            if (!$buf) return;
            $epics = array_values(array_filter(array_map(fn ($b) => $b[1]['epic_no'] ?? null, $buf)));
            $existing = self::voters()->whereIn('epic_no', $epics)->get()->keyBy('epic_no')->all();
            $seen = DB::table('roll_update_rows')->where('roll_update_id', $u->id)->whereIn('epic_no', $epics)->pluck('epic_no')->flip()->all();
            $rows = [];
            foreach ($buf as [$label, $data]) {
                $epic = $data['epic_no'] ?? null;
                if ($epic !== null && isset($seen[$epic])) { $errors[] = t('import.row', $label) . ': ' . t('roll.error.duplicate', $epic); continue; }
                if ($epic !== null) $seen[$epic] = true;
                if (isset($data['booth_no'])) $booths[(int) $data['booth_no']] = true;
                $row = ['roll_update_id' => $u->id, 'row_no' => $label, 'epic_no' => $epic, 'name' => mb_substr((string) ($data['name'] ?? $data['name_local'] ?? ''), 0, 150),
                    'booth_no' => $data['booth_no'] ?? null, 'serial_no' => $data['serial_no'] ?? null, 'data' => self::json($data),
                    'action' => 'NEW', 'voter_id' => null, 'old_booth_no' => null, 'old_serial_no' => null, 'has_survey' => false, 'changes' => null];
                if ($epic !== null && ($v = $existing[$epic] ?? null)) {
                    $changes = self::diff($v, $data);
                    $row = array_merge($row, ['action' => self::classify($v, $changes), 'voter_id' => $v->id, 'old_booth_no' => $v->booth_no,
                        'old_serial_no' => $v->serial_no, 'has_survey' => self::hasSurvey($v), 'changes' => $changes ? self::json($changes) : null]);
                }
                $rows[] = $row;
            }
            if ($rows) DB::table('roll_update_rows')->insert($rows);
            $buf = [];
        };
        $found = $this->reader->eachRow($path, $assembly, $assembly, function ($label, $data) use (&$buf, &$errors, $assembly, $flush) {
            if ((int) ($data['assembly_no'] ?? 0) !== $assembly) { $errors[] = t('import.row', $label) . ': ' . t('import.error.assembly', $assembly); return; }
            if (empty($data['booth_no'])) { $errors[] = t('import.row', $label) . ': ' . t('roll.error.booth'); return; }
            $buf[] = [$label, self::rollData($data)];
            if (count($buf) >= self::CHUNK) $flush();
        }, $errors);
        $flush();
        if (!$found) $errors[] = t('import.error.header');
        $booths = array_keys($booths);
        sort($booths);
        $u->booths = implode(',', $booths);
        if ($booths) {
            $this->matchByName($u, $booths);
            $this->findDeleted($u, $booths);
        }
        $u->errors = self::json(array_slice($errors, 0, 300));
        $u->counts = self::json($this->counts($u, $booths, count($errors)));
        $u->save();
        return $u;
    }

    /** Rows without an EPIC match: one unmatched voter of the same booths with the same name, relative and gender (age within 3 years). */
    private function matchByName(RollUpdate $u, array $booths): void
    {
        $matched = DB::table('roll_update_rows')->where('roll_update_id', $u->id)->whereNotNull('voter_id')->select('voter_id');
        $pool = [];
        self::voters()->where('assembly_no', $u->assembly_no)->whereIn('booth_no', $booths)->whereNotIn('id', $matched)
            ->orderBy('id')->select(array_merge(['id', 'deleted'], self::ROLL_FIELDS, self::SURVEY_FIELDS))
            ->chunk(2000, function ($vs) use (&$pool) {
                foreach ($vs as $v) $pool[self::nameKey($v->name ?: $v->name_local, $v->relative_name ?: $v->relative_name_local, $v->gender)][] = $v;
            });
        if (!$pool) return;
        DB::table('roll_update_rows')->where('roll_update_id', $u->id)->where('action', 'NEW')->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$pool) {
                foreach ($rows as $r) {
                    $d = json_decode($r->data, true);
                    $k = self::nameKey($d['name'] ?? $d['name_local'] ?? '', $d['relative_name'] ?? $d['relative_name_local'] ?? '', $d['gender'] ?? null);
                    $cands = array_values(array_filter($pool[$k] ?? [], fn ($v) => $v->age === null || !isset($d['age']) || abs((int) $v->age - (int) $d['age']) <= 3));
                    if (count($cands) !== 1 || trim($k, '|') === '') continue;
                    $v = $cands[0];
                    $pool[$k] = array_values(array_filter($pool[$k], fn ($x) => $x->id !== $v->id));
                    $changes = self::diff($v, $d);
                    DB::table('roll_update_rows')->where('id', $r->id)->update(['action' => 'NAMEMATCH', 'voter_id' => $v->id, 'old_booth_no' => $v->booth_no,
                        'old_serial_no' => $v->serial_no, 'has_survey' => self::hasSurvey($v), 'changes' => $changes ? self::json($changes) : null]);
                }
            });
    }

    /** Voters of the booths in the new roll that are not in it: to be marked deleted (only booths present in the file are checked). */
    private function findDeleted(RollUpdate $u, array $booths): void
    {
        $matched = DB::table('roll_update_rows')->where('roll_update_id', $u->id)->whereNotNull('voter_id')->select('voter_id');
        self::voters()->where('assembly_no', $u->assembly_no)->whereIn('booth_no', $booths)->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))
            ->whereNotIn('id', $matched)->orderBy('id')->select(array_merge(['id', 'epic_no', 'name', 'name_local', 'booth_no', 'serial_no'], self::SURVEY_FIELDS))
            ->chunk(self::CHUNK, function ($vs) use ($u) {
                $rows = [];
                foreach ($vs as $v) $rows[] = ['roll_update_id' => $u->id, 'row_no' => null, 'action' => 'DELETED', 'voter_id' => $v->id, 'epic_no' => $v->epic_no,
                    'name' => mb_substr((string) ($v->name ?: $v->name_local), 0, 150), 'booth_no' => $v->booth_no, 'serial_no' => $v->serial_no,
                    'old_booth_no' => $v->booth_no, 'old_serial_no' => $v->serial_no, 'has_survey' => self::hasSurvey($v), 'data' => null, 'changes' => null];
                DB::table('roll_update_rows')->insert($rows);
            });
    }

    /** Totals per action, survey data kept / at risk, and the booth-wise table (kept after apply, when the SAME rows are dropped). */
    private function counts(RollUpdate $u, array $booths, int $errors): array
    {
        $q = fn () => DB::table('roll_update_rows')->where('roll_update_id', $u->id);
        $by = $q()->selectRaw('action, count(*) as n, sum(case when has_survey = 1 then 1 else 0 end) as s')->groupBy('action')->get()->keyBy('action');
        $c = ['actions' => [], 'errors' => $errors, 'surveyKept' => 0, 'surveyDeleted' => (int) ($by['DELETED']->s ?? 0)];
        foreach (self::ACTIONS as $a) $c['actions'][$a] = (int) ($by[$a]->n ?? 0);
        foreach ($by as $a => $x) if ($a !== 'DELETED' && $a !== 'NEW') $c['surveyKept'] += (int) $x->s;
        $c['inRoll'] = array_sum($c['actions']) - $c['actions']['DELETED'];
        $now = self::voters()->where('assembly_no', $u->assembly_no)->whereIn('booth_no', $booths ?: [-1])
            ->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'))->selectRaw('booth_no, count(*) as n')->groupBy('booth_no')->pluck('n', 'booth_no');
        $per = $q()->where('action', '<>', 'DELETED')->selectRaw('booth_no, action, count(*) as n')->groupBy('booth_no', 'action')->get();
        $del = $q()->where('action', 'DELETED')->selectRaw('booth_no, count(*) as n')->groupBy('booth_no')->pluck('n', 'booth_no');
        $out = $q()->where('action', 'SHIFTED')->whereNotNull('old_booth_no')->selectRaw('old_booth_no, count(*) as n')->groupBy('old_booth_no')->pluck('n', 'old_booth_no');
        $t = [];
        foreach ($booths as $b) $t[$b] = ['now' => (int) ($now[$b] ?? 0), 'roll' => 0, 'NEW' => 0, 'DELETED' => (int) ($del[$b] ?? 0), 'in' => 0,
            'out' => 0, 'CHANGED' => 0, 'RESTORED' => 0, 'NAMEMATCH' => 0];
        foreach ($per as $p) {
            $b = (int) $p->booth_no;
            if (!isset($t[$b])) continue;
            $t[$b]['roll'] += (int) $p->n;
            if ($p->action === 'SHIFTED') $t[$b]['in'] += (int) $p->n;
            elseif (isset($t[$b][$p->action])) $t[$b][$p->action] += (int) $p->n;
        }
        foreach ($out as $b => $n) if (isset($t[(int) $b])) $t[(int) $b]['out'] = (int) $n;
        $c['booths'] = $t;
        return $c;
    }

    // ------------------------------------------------------------------ 2. apply

    /**
     * Writes the new roll into the voter list in chunks. Each row remembers the values it replaced (column "before"),
     * so a stopped run (time limit on shared hosting) continues where it left off, and undo() can put them back.
     */
    public function apply(RollUpdate $u, ?string $user): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
        $done = ['updated' => 0, 'added' => 0, 'deleted' => 0, 'pannaCleared' => 0, 'grievances' => 0, 'failed' => []];
        $now = now();
        $this->planAreas($u);
        DB::table('roll_update_rows')->where('roll_update_id', $u->id)->whereNull('before')
            ->where(fn ($q) => $q->where('action', '<>', 'SAME')->orWhereNotNull('changes'))
            ->orderBy('id')->chunkById(self::CHUNK, function ($rows) use (&$done, $now, $u) {
                $voters = self::voters()->whereIn('id', $rows->pluck('voter_id')->filter()->all())->get()->keyBy('id');
                DB::transaction(function () use ($rows, $voters, &$done, $now, $u) {
                    foreach ($rows as $r) {
                        if ($r->action === 'NEW') {
                            $d = json_decode($r->data, true) + ['assembly_no' => $u->assembly_no];
                            try {
                                $id = self::voters()->insertGetId($d + ['relation_type' => 'FATHER', 'deleted' => false, 'created_at' => $now, 'updated_at' => $now]);
                                DB::table('roll_update_rows')->where('id', $r->id)->update(['voter_id' => $id, 'before' => '{"new":true}']);
                                $done['added']++;
                            } catch (\Throwable $e) { $done['failed'][] = ($r->epic_no ?: $r->name) . ': ' . $e->getMessage(); }
                            continue;
                        }
                        $v = $voters[$r->voter_id] ?? null;
                        if (!$v) { DB::table('roll_update_rows')->where('id', $r->id)->update(['before' => '{"gone":true}']); continue; }
                        if ($r->action === 'DELETED') {
                            $upd = ['deleted' => true];
                        } else {
                            $upd = json_decode($r->data, true);
                            foreach ($upd as $f => $val) if (self::same($v->$f ?? null, $val, $f)) unset($upd[$f]);
                            if ($v->deleted) $upd['deleted'] = false;
                            // shifted to another booth: the old panna pramukh no longer covers this voter
                            if (isset($upd['booth_no']) && $v->worker_id !== null) { $upd['worker_id'] = null; $done['pannaCleared']++; }
                        }
                        $before = [];
                        foreach ($upd as $f => $_) $before[$f] = $v->$f;
                        try {
                            if ($upd) self::voters()->where('id', $v->id)->update($upd + ['updated_at' => $now]);
                            // the voter's grievances follow them to the new booth / ward
                            $g = array_intersect_key($upd, ['booth_no' => 1, 'ward_no' => 1]);
                            if ($g && ($n = DB::table('grievances')->where('voter_id', $v->id)->update($g))) {
                                $before['__grv'] = array_intersect_key($before, $g);
                                $done['grievances'] += $n;
                            }
                            DB::table('roll_update_rows')->where('id', $r->id)->update(['before' => self::json((object) $before)]);
                            $r->action === 'DELETED' ? $done['deleted']++ : $done['updated']++;
                        } catch (\Throwable $e) { $done['failed'][] = ($r->epic_no ?: $r->name) . ': ' . $e->getMessage(); }
                    }
                });
            });
        if (!$done['failed']) {
            $done['areas'] = $this->moveAreas($u);
            // rows with nothing to undo are not needed any more (the booth table is kept in "counts")
            DB::table('roll_update_rows')->where('roll_update_id', $u->id)->where('action', 'SAME')->whereNull('changes')->delete();
            $u->update(['status' => 'APPLIED', 'applied_by' => $user, 'applied_at' => $now]);
        }
        return $done;
    }

    /**
     * Logins limited to a serial range of one booth (a panna page): remembers which voters each range covers now,
     * before any voter is changed (kept in "counts", so a resumed apply uses the same plan).
     */
    private function planAreas(RollUpdate $u): void
    {
        $c = $u->countsData();
        if (array_key_exists('areaPlan', $c) || !\Illuminate\Support\Facades\Schema::hasColumn('app_users', 'scope_serial_from')) return;
        $plan = [];
        foreach (DB::table('app_users')->where('assembly_no', $u->assembly_no)->where(fn ($q) => $q->whereNotNull('scope_serial_from')->orWhereNotNull('scope_serial_to'))
                     ->get(['id', 'username', 'scope_booths', 'scope_serial_from', 'scope_serial_to']) as $usr) {
            $booths = \App\Support\Area::ints($usr->scope_booths);
            if (count($booths) !== 1 || !in_array($booths[0], $u->boothList(), true)) continue;   // booth not in the new roll: nothing changes
            $ids = self::voters()->where('assembly_no', $u->assembly_no)->where('booth_no', $booths[0])
                ->when($usr->scope_serial_from !== null, fn ($q) => $q->where('serial_no', '>=', $usr->scope_serial_from))
                ->when($usr->scope_serial_to !== null, fn ($q) => $q->where('serial_no', '<=', $usr->scope_serial_to))
                ->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))->pluck('id')->all();
            $plan[] = ['user' => $usr->id, 'username' => $usr->username, 'booth' => $booths[0], 'from' => $usr->scope_serial_from,
                'to' => $usr->scope_serial_to, 'voters' => $ids];
        }
        $c['areaPlan'] = $plan;
        $u->counts = self::json($c);
        $u->save();
    }

    /**
     * After the update: each planned range becomes the new first to last serial no. of the same voters still in that booth
     * (new voters in between are included, as they are on the same roll page). A range with none of its voters left is not changed.
     */
    private function moveAreas(RollUpdate $u): array
    {
        $c = $u->countsData();
        $out = [];
        foreach ($c['areaPlan'] ?? [] as $p) {
            $q = self::voters()->whereIn('id', $p['voters'] ?: [0])->where('assembly_no', $u->assembly_no)->where('booth_no', $p['booth'])
                ->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'));
            $from = (clone $q)->min('serial_no');
            $to = (clone $q)->max('serial_no');
            $row = ['username' => $p['username'], 'booth' => $p['booth'], 'from' => $p['from'], 'to' => $p['to'], 'newFrom' => null, 'newTo' => null];
            if ($from !== null) {
                $row['newFrom'] = (int) $from;
                $row['newTo'] = (int) $to;
                DB::table('app_users')->where('id', $p['user'])->update(['scope_serial_from' => (int) $from, 'scope_serial_to' => (int) $to]);
            }
            $out[] = $row;
        }
        $c['areas'] = $out;
        $u->counts = self::json($c);
        $u->save();
        return $out;
    }

    // ------------------------------------------------------------------ 3. undo

    /** Only the latest applied update of an assembly can be undone (older ones would overwrite newer changes). */
    public function canUndo(RollUpdate $u): bool
    {
        return $u->status === 'APPLIED' && !RollUpdate::where('assembly_no', $u->assembly_no)->where('status', 'APPLIED')->where('id', '>', $u->id)->exists();
    }

    public function undo(RollUpdate $u): array
    {
        @set_time_limit(0);
        $res = ['restored' => 0, 'removed' => 0];
        DB::table('roll_update_rows')->where('roll_update_id', $u->id)->whereNotNull('before')->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$res) {
                DB::transaction(function () use ($rows, &$res) {
                    foreach ($rows as $r) {
                        $b = json_decode($r->before, true) ?: [];
                        if (!empty($b['new'])) {
                            if ($r->voter_id) {
                                DB::table('poll_marks')->where('voter_id', $r->voter_id)->delete();
                                self::voters()->where('id', $r->voter_id)->delete();
                            }
                            DB::table('roll_update_rows')->where('id', $r->id)->update(['voter_id' => null]);
                            $res['removed']++;
                        } elseif ($b && empty($b['gone'])) {
                            if (isset($b['__grv'])) {
                                if ($b['__grv']) DB::table('grievances')->where('voter_id', $r->voter_id)->update($b['__grv']);
                                unset($b['__grv']);
                            }
                            if ($b) self::voters()->where('id', $r->voter_id)->update($b + ['updated_at' => now()]);
                            $res['restored']++;
                        }
                        DB::table('roll_update_rows')->where('id', $r->id)->update(['before' => null]);
                    }
                });
            }, 'id');
        foreach ($u->countsData()['areaPlan'] ?? [] as $p) {
            DB::table('app_users')->where('id', $p['user'])->update(['scope_serial_from' => $p['from'], 'scope_serial_to' => $p['to']]);
        }
        $u->update(['status' => 'UNDONE', 'undone_at' => now()]);
        return $res;
    }

    public function discard(RollUpdate $u): void
    {
        DB::table('roll_update_rows')->where('roll_update_id', $u->id)->delete();
        $u->update(['status' => 'DISCARDED']);
    }

    // ------------------------------------------------------------------ report

    /** Every voter that changes (not the unchanged ones) as an .xlsx file path; the caller deletes it. */
    public function reportFile(RollUpdate $u): string
    {
        $file = tempnam(sys_get_temp_dir(), 'roll') . '.xlsx';
        $w = new \OpenSpout\Writer\XLSX\Writer();
        $w->openToFile($file);
        $bold = (new \OpenSpout\Common\Entity\Style\Style())->setFontBold();
        $w->addRow(\OpenSpout\Common\Entity\Row::fromValues([t('roll.reportTitle', $u->assembly_no, $u->file_name)], (new \OpenSpout\Common\Entity\Style\Style())->setFontBold()->setFontSize(13)));
        $w->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));
        $w->addRow(\OpenSpout\Common\Entity\Row::fromValues(array_map('t', ['roll.col.action', 'col.boothNo', 'col.serialNo', 'col.epicNo', 'col.name',
            'roll.col.oldBooth', 'roll.col.oldSerial', 'roll.col.changes', 'roll.col.survey']), $bold));
        foreach (self::ACTIONS as $a) {
            if ($a === 'SAME') continue;
            DB::table('roll_update_rows')->where('roll_update_id', $u->id)->where('action', $a)->orderBy('booth_no')->orderBy('serial_no')->orderBy('id')
                ->chunk(1000, function ($rows) use ($w, $a) {
                    foreach ($rows as $r) $w->addRow(\OpenSpout\Common\Entity\Row::fromValues([t('roll.action.' . $a), $r->booth_no, $r->serial_no, $r->epic_no, $r->name,
                        $r->old_booth_no, $r->old_serial_no, self::changesText($r->changes), $r->has_survey ? t('common.yes') : '']));
                });
        }
        $errors = json_decode((string) $u->errors, true) ?: [];
        if ($errors) {
            $w->addNewSheetAndMakeItCurrent()->setName(mb_substr(t('roll.errors'), 0, 31));
            foreach ($errors as $e) $w->addRow(\OpenSpout\Common\Entity\Row::fromValues([$e]));
        }
        $w->close();
        return $file;
    }

    /** "Name: Ram → Raam; Age: 30 → 31" */
    public static function changesText(?string $json): string
    {
        $c = json_decode((string) $json, true) ?: [];
        $parts = [];
        foreach ($c as $f => [$old, $new]) $parts[] = t($f === 'relation_type' ? 'col.relation' : 'field.' . \Illuminate\Support\Str::camel($f)) . ': ' . ($old ?? '-') . ' → ' . ($new ?? '-');
        return implode('; ', $parts);
    }
}
