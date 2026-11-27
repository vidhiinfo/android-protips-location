<?php

namespace App\Services\Org;

use App\Models\Mandal;
use App\Models\Worker;
use App\Support\WorkerRoles as R;
use App\Services\Voters\VoterService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organisation chain: Lok Sabha in-charge -> assembly in-charge -> mandal in-charge -> booth president ->
 * booth agents / panna pramukhs. Who works under whom follows from role, assembly, mandal and booth.
 * Gaps (missing in-charges, booths without president or panna pramukh) are worked out here too.
 */
class OrganisationService
{
    public function __construct(private VoterService $voters) {}

    public static function available(): bool { return Schema::hasTable('mandals'); }

    /** Active workers of the given assemblies (plus Lok Sabha in-charges), grouped by assembly. */
    private function people(array $assemblies): Collection
    {
        return Worker::where('active', true)->whereIn('assembly_no', array_merge($assemblies, [Worker::ALL_ASSEMBLIES]))->orderBy('name')->get();
    }

    /** Voters per booth, and voters looked after by a panna pramukh, for the given assemblies. */
    private function boothVoters(array $assemblies): array
    {
        $out = [];
        $q = DB::table('voters as v')->leftJoin('workers as w', 'w.id', '=', 'v.worker_id')->whereIn('v.assembly_no', $assemblies)
            ->where(fn ($x) => $x->where('v.deleted', false)->orWhereNull('v.deleted'))
            ->selectRaw('v.assembly_no, v.booth_no, count(*) as n, sum(case when w.role in (' . R::sqlList('PANNA_PRAMUKH') . ') and w.active = 1 then 1 else 0 end) as panna')
            ->groupBy('v.assembly_no', 'v.booth_no');
        foreach ($q->get() as $r) $out[(int) $r->assembly_no][(int) $r->booth_no] = ['n' => (int) $r->n, 'panna' => (int) $r->panna];
        return $out;
    }

    /** Open / done tasks per worker. */
    private function taskCounts(array $workerIds): array
    {
        if (!$workerIds || !Schema::hasTable('worker_tasks')) return [];
        $out = [];
        foreach (DB::table('worker_tasks')->whereIn('worker_id', $workerIds)->selectRaw("worker_id, sum(case when status = 'DONE' then 1 else 0 end) as done, count(*) as total")
                     ->groupBy('worker_id')->get() as $r) $out[(int) $r->worker_id] = ['done' => (int) $r->done, 'total' => (int) $r->total];
        return $out;
    }

    /**
     * Summary per assembly for the Organisation page and the Lok Sabha overview:
     * in-charges, mandals, booths with a president / panna pramukh, panna coverage, gaps, tasks.
     */
    public function summary(array $assemblies): array
    {
        $people = $this->people($assemblies);
        $mandals = Mandal::whereIn('assembly_no', $assemblies)->where('active', true)->get()->groupBy('assembly_no');
        $bv = $this->boothVoters($assemblies);
        $tasks = $this->taskCounts($people->pluck('id')->all());
        $out = [];
        foreach ($assemblies as $a) {
            $mine = $people->where('assembly_no', $a);
            $booths = array_keys($bv[$a] ?? []);
            $pres = $mine->whereIn('role', R::like('BOOTH_PRESIDENT'))->pluck('booth_no')->filter()->map(fn ($b) => (int) $b)->unique()->all();
            $pannaBooths = $mine->whereIn('role', R::like('PANNA_PRAMUKH'))->pluck('booth_no')->filter()->map(fn ($b) => (int) $b)->unique()->all();
            $ms = $mandals[$a] ?? collect();
            $inMandal = $ms->flatMap(fn ($m) => $m->boothList())->unique()->all();
            $mandalHeads = $mine->whereIn('role', R::like('MANDAL_INCHARGE'))->pluck('mandal_id')->filter()->unique()->all();
            $voters = array_sum(array_column($bv[$a] ?? [], 'n')); $panna = array_sum(array_column($bv[$a] ?? [], 'panna'));
            $t = ['done' => 0, 'total' => 0];
            foreach ($mine as $w) { $t['done'] += $tasks[$w->id]['done'] ?? 0; $t['total'] += $tasks[$w->id]['total'] ?? 0; }
            $out[$a] = [
                'incharge' => $mine->whereIn('role', R::like('AC_INCHARGE'))->values(),
                'mandals' => $ms->count(), 'mandalsNoHead' => $ms->whereNotIn('id', $mandalHeads)->count(),
                'booths' => count($booths), 'boothsNoMandal' => count(array_diff($booths, $inMandal)),
                'withPresident' => count(array_intersect($booths, $pres)), 'withPanna' => count(array_intersect($booths, $pannaBooths)),
                'voters' => $voters, 'pannaVoters' => $panna, 'workers' => $mine->count(), 'tasks' => $t,
            ];
        }
        return $out;
    }

    /** Lok Sabha in-charges. */
    public function lokSabhaHeads(): Collection
    {
        return Worker::where('active', true)->whereIn('role', R::like('LS_INCHARGE'))->orderBy('name')->get();
    }

    /** One assembly in detail: its mandals (with in-charge and gaps) and every booth (with mandal, president, agents, panna pramukhs). */
    public function assembly(int $a): array
    {
        $people = Worker::where('active', true)->where('assembly_no', $a)->orderBy('name')->get();
        $mandals = Mandal::where('assembly_no', $a)->orderBy('name')->get();
        $bv = $this->boothVoters([$a])[$a] ?? [];
        $tasks = $this->taskCounts($people->pluck('id')->all());
        $mandalOf = [];
        foreach ($mandals->where('active', true) as $m) foreach ($m->boothList() as $b) $mandalOf[$b] ??= $m;
        $booths = [];
        foreach ($this->voters->booths($a) as $b) {
            $here = $people->where('booth_no', $b);
            $booths[$b] = ['booth' => $b, 'mandal' => $mandalOf[$b] ?? null, 'voters' => $bv[$b]['n'] ?? 0, 'pannaVoters' => $bv[$b]['panna'] ?? 0,
                'president' => $here->whereIn('role', R::like('BOOTH_PRESIDENT'))->values(), 'agents' => $here->whereIn('role', R::like('BOOTH_AGENT'))->values(),
                'panna' => $here->whereIn('role', R::like('PANNA_PRAMUKH'))->values()];
        }
        $mrows = [];
        foreach ($mandals as $m) {
            $list = array_values(array_intersect($m->boothList(), array_keys($booths)));
            $t = ['done' => 0, 'total' => 0];
            foreach ($people->filter(fn ($w) => $w->mandal_id === $m->id || in_array((int) $w->booth_no, $list, true)) as $w) {
                $t['done'] += $tasks[$w->id]['done'] ?? 0; $t['total'] += $tasks[$w->id]['total'] ?? 0;
            }
            $mrows[] = ['mandal' => $m, 'head' => $people->whereIn('role', R::like('MANDAL_INCHARGE'))->where('mandal_id', $m->id)->values(), 'booths' => $list,
                'noPresident' => count(array_filter($list, fn ($b) => $booths[$b]['president']->isEmpty())),
                'voters' => array_sum(array_map(fn ($b) => $booths[$b]['voters'], $list)), 'tasks' => $t];
        }
        return ['incharge' => $people->whereIn('role', R::like('AC_INCHARGE'))->values(), 'mandals' => $mrows, 'booths' => $booths,
            'unplaced' => array_values(array_filter(array_keys($booths), fn ($b) => !isset($mandalOf[$b])))];
    }

    /** Booths a worker looks after (empty = the whole assembly / all assemblies, depending on the role). */
    public function boothsOf(Worker $w): array
    {
        return match ($w->base()) {
            'LS_INCHARGE', 'AC_INCHARGE' => [],
            'MANDAL_INCHARGE' => $w->mandal_id ? (Mandal::find($w->mandal_id)?->boothList() ?? []) : [],
            default => $w->booth_no ? [(int) $w->booth_no] : [],
        };
    }

    /** Everyone who works under this person (active workers only). */
    public function under(Worker $w): Collection
    {
        $q = Worker::where('active', true)->where('id', '<>', $w->id);
        $lower = fn ($lvl) => array_merge(...array_map(fn ($b) => R::like($b), array_keys(array_filter(Worker::LEVEL, fn ($l) => $l > $lvl))));
        switch ($w->base()) {
            case 'LS_INCHARGE': return $q->whereIn('role', $lower(0))->get();
            case 'AC_INCHARGE': return $q->where('assembly_no', $w->assembly_no)->whereIn('role', $lower(1))->get();
            case 'MANDAL_INCHARGE':
                $booths = $this->boothsOf($w);
                return $booths ? $q->where('assembly_no', $w->assembly_no)->whereIn('booth_no', $booths)->whereIn('role', $lower(2))->get() : collect();
            case 'BOOTH_PRESIDENT':
                return $w->booth_no ? $q->where('assembly_no', $w->assembly_no)->where('booth_no', $w->booth_no)->whereIn('role', $lower(3))->get() : collect();
            default: return collect();
        }
    }

    /**
     * Login access that matches a worker's place in the chain: [assembly_no, scope_booths, serial_from, serial_to].
     * Panna pramukh: their booth and the serial range of the voters given to them.
     */
    public function accessFor(Worker $w): array
    {
        if ($w->base() === 'LS_INCHARGE') return [null, null, null, null];
        $booths = $this->boothsOf($w);
        $from = $to = null;
        if ($w->base() === 'PANNA_PRAMUKH' && count($booths) === 1) {
            $r = DB::table('voters')->where('worker_id', $w->id)->where('booth_no', $booths[0])->selectRaw('min(serial_no) as lo, max(serial_no) as hi')->first();
            if ($r && $r->lo) { $from = (int) $r->lo; $to = (int) $r->hi; }
        }
        return [(int) $w->assembly_no, $booths ? implode(', ', $booths) : null, $from, $to];
    }
}
