<?php

namespace App\Http\Controllers;

use App\Models\AppUser;
use App\Models\Mandal;
use App\Models\Worker;
use App\Services\Org\LokSabhaService;
use App\Services\Org\OrganisationService;
use App\Services\Workers\TaskService;
use App\Services\Voters\VoterService;
use App\Support\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Organisation chain (Lok Sabha in-charge down to panna pramukh), mandals, and the Lok Sabha overview. */
class OrganisationController extends Controller
{
    public function __construct(private VoterService $voters, private OrganisationService $org, private LokSabhaService $ls) {}

    /** Assemblies this login may see: [no => name]. A login limited to booths sees only its assembly. */
    private function myAssemblies(): array
    {
        return array_filter($this->voters->assemblies(), fn ($n, $no) => $this->user()->allows($no), ARRAY_FILTER_USE_BOTH);
    }

    private function assemblyOr404($a): int
    {
        $a = (int) $a;
        abort_unless($a && array_key_exists($a, $this->myAssemblies()), 404, t('error.notFound'));
        return $a;
    }

    // ------------------------------------------------------------------ Lok Sabha overview
    public function lokSabha()
    {
        $as = $this->myAssemblies();
        return view('organisation.loksabha', ['data' => $this->ls->overview($as), 'heads' => $this->org->lokSabhaHeads()]);
    }

    // ------------------------------------------------------------------ organisation
    public function index()
    {
        $as = $this->myAssemblies();
        return view('organisation.index', ['assemblies' => $as, 'summary' => $this->org->summary(array_keys($as)), 'heads' => $this->org->lokSabhaHeads()]);
    }

    public function assembly(int $a)
    {
        $a = $this->assemblyOr404($a);
        return view('organisation.assembly', ['a' => $a, 'name' => $this->myAssemblies()[$a], 'data' => $this->org->assembly($a),
            'kinds' => TaskService::KINDS]);
    }

    public function saveMandal(Request $r, int $a)
    {
        $a = $this->assemblyOr404($a);
        $d = $r->validate(['id' => 'nullable|integer', 'name' => 'required|string|max:100', 'booths' => 'nullable|string|max:1000']);
        $known = $this->voters->booths($a);
        $booths = array_values(array_intersect(Mandal::parseBooths($d['booths'] ?? ''), $known));
        $m = !empty($d['id']) ? Mandal::where('assembly_no', $a)->findOrFail($d['id']) : new Mandal(['assembly_no' => $a, 'created_at' => now(), 'active' => true]);
        $m->fill(['name' => trim($d['name']), 'booths' => $booths ? implode(', ', $booths) : null])->save();
        $this->audit()->log(!empty($d['id']) ? 'UPDATE' : 'CREATE', 'MANDAL', $m->id, $m->name . ': ' . count($booths) . ' booths');
        return redirect('/organisation/' . $a)->with('success', t('org.mandalSaved', $m->name, count($booths)));
    }

    public function deleteMandal(int $a, int $id)
    {
        $a = $this->assemblyOr404($a);
        $m = Mandal::where('assembly_no', $a)->findOrFail($id);
        Worker::where('mandal_id', $m->id)->update(['mandal_id' => null]);
        $m->delete();
        $this->audit()->log('DELETE', 'MANDAL', $id, $m->name);
        return redirect('/organisation/' . $a)->with('success', t('common.deleted'));
    }

    /** Set the linked login's access area to this person's place in the chain (needs user administration). */
    public function access(int $id)
    {
        abort_unless($this->user()->can('USER_ADMIN'), 403);
        $w = Worker::findOrFail($id);
        abort_unless($w->assembly_no === Worker::ALL_ASSEMBLIES ? !$this->user()->scoped() : $this->user()->allows($w->assembly_no), 404);
        $u = $w->username ? AppUser::where('username', $w->username)->first() : null;
        if (!$u) return back()->with('error', t('org.noLogin'));
        [$asm, $booths, $from, $to] = $this->org->accessFor($w);
        $u->forceFill(['assembly_no' => $asm, 'scope_booths' => $booths, 'scope_wards' => null, 'scope_serial_from' => $from, 'scope_serial_to' => $to])->save();
        $this->audit()->log('UPDATE', 'USER', $u->id, "access set from organisation ({$w->role}): assembly " . ($asm ?? 'all') . ($booths ? "; booths $booths" : '') . ($from ? "; serial $from-$to" : ''));
        return back()->with('success', t('org.accessSet', $u->username));
    }

    /** One task for every active person under this one (optionally only some roles). */
    public function teamTask(Request $r)
    {
        $w = Worker::findOrFail((int) $r->input('leader_id'));
        abort_unless($w->assembly_no === Worker::ALL_ASSEMBLIES ? !$this->user()->scoped() : $this->user()->allows($w->assembly_no), 404);
        $d = $r->validate(['title' => 'required|string|max:200', 'kind' => 'required|in:' . implode(',', TaskService::KINDS),
            'due_date' => 'nullable|date_format:Y-m-d', 'target' => 'nullable|integer|min:1|max:100000', 'roles' => 'nullable|array', 'roles.*' => 'in:' . implode(',', Worker::ROLES)]);
        $team = $this->org->under($w)->filter(fn ($x) => empty($d['roles']) || in_array($x->role, $d['roles'], true))
            ->filter(fn ($x) => $x->assembly_no !== Worker::ALL_ASSEMBLIES && $this->user()->allows($x->assembly_no));
        $now = now(); $rows = [];
        foreach ($team as $x) $rows[] = ['title' => trim($d['title']), 'kind' => $d['kind'], 'worker_id' => $x->id, 'username' => $x->username, 'assembly_no' => $x->assembly_no,
            'booth_no' => $x->booth_no, 'target' => $d['target'] ?? null, 'due_date' => $d['due_date'] ?? null, 'status' => 'OPEN',
            'notes' => t('org.fromLeader', $w->name), 'created_by' => $this->user()->username(), 'created_at' => $now];
        foreach (array_chunk($rows, 200) as $chunk) DB::table('worker_tasks')->insert($chunk);
        $this->audit()->log('CREATE', 'TASK', null, "team task '{$d['title']}' for " . count($rows) . " people under {$w->name}");
        return back()->with('success', t('org.teamTaskDone', count($rows)));
    }
}
