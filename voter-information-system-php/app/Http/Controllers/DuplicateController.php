<?php

namespace App\Http\Controllers;

use App\Models\Voter;
use App\Services\Voters\MergeService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Voters > Duplicates: review likely duplicates side by side, keep one and merge the others into it (undoable). */
class DuplicateController extends Controller
{
    private const PAGE = 50;

    public function __construct(private VoterService $voters, private MergeService $merge) {}

    public function index(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $b = $r->query('boothNo') ? (int) $r->query('boothNo') : null;
        $sameAge = $r->query('anyAge') ? false : true;
        $page = max(1, (int) $r->query('page', 1));
        $total = 0;
        $groups = $a ? $this->merge->groups($a, $b, $sameAge, ($page - 1) * self::PAGE, self::PAGE, $total) : [];
        $merges = DB::table('voter_merges')->where('assembly_no', $a)->orderByDesc('id')->limit(30)->get();
        $names = Voter::withoutGlobalScopes()->whereIn('id', $merges->pluck('keep_id')->merge($merges->pluck('drop_id'))->all() ?: [0])->get(['id', 'name', 'epic_no'])->keyBy('id');
        return view('voters.duplicates', ['assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'booths' => $a ? $this->voters->booths($a) : [],
            'boothNo' => $b, 'sameAge' => $sameAge, 'groups' => $groups, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PAGE)),
            'merges' => $merges, 'names' => $names, 'fields' => MergeService::fields()]);
    }

    public function merge(Request $r)
    {
        $keep = Voter::find((int) $r->input('keep'));
        abort_if(!$keep || !$this->user()->allowsVoter($keep), 404, t('error.notFound'));
        $n = 0;
        foreach ((array) $r->input('drop', []) as $id) {
            $d = Voter::find((int) $id);
            if (!$d || $d->id === $keep->id || !$this->user()->allowsVoter($d) || $d->deleted || $d->assembly_no !== $keep->assembly_no) continue;
            $this->merge->merge($keep->fresh(), $d, $this->user()->username());
            $n++;
        }
        return back()->with($n ? 'success' : 'error', $n ? t('dup.merged', $n, $keep->name) : t('dup.pickOne'));
    }

    public function undo(int $id)
    {
        $m = DB::table('voter_merges')->find($id);
        abort_if(!$m || !$this->user()->allows($m->assembly_no), 404, t('error.notFound'));
        try { $kept = $this->merge->undo($id, $this->user()->username()); }
        catch (\InvalidArgumentException) { return back()->with('error', t('dup.alreadyUndone')); }
        return back()->with('success', t('dup.undone') . ($kept ? ' ' . t('dup.keptFields', implode(', ', $kept)) : ''));
    }
}
