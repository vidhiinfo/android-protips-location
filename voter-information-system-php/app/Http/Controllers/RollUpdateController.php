<?php

namespace App\Http\Controllers;

use App\Models\RollUpdate;
use App\Services\Voters\RollUpdateService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Roll update: compare a new voter roll with the assembly, check the changes, apply (and undo) without losing survey data. */
class RollUpdateController extends Controller
{
    public function __construct(private RollUpdateService $rolls, private VoterService $voters) {}

    private function find(int $id): RollUpdate
    {
        $u = RollUpdate::find($id);
        abort_if(!$u || !$this->user()->allows($u->assembly_no), 404, t('error.notFound'));
        return $u;
    }

    /** A user limited to some wards / booths sees only part of the assembly: the roll update needs the whole assembly. */
    private function blocked(): ?string { return \App\Support\Area::current() ? t('roll.error.area') : null; }

    public function index(Request $r, ?string $error = null)
    {
        $list = RollUpdate::query()->when($this->user()->scoped(), fn ($q) => $q->where('assembly_no', $this->user()->workAssembly()))
            ->orderByDesc('id')->limit(50)->get();
        return view('rollupdate.index', ['list' => $list, 'assemblies' => $this->voters->assemblies(),
            'defaultAssembly' => $this->user()->workAssembly(), 'error' => $error ?? $this->blocked()]);
    }

    public function upload(Request $r)
    {
        if ($b = $this->blocked()) return $this->index($r, $b);
        $file = $r->file('file');
        $assembly = $this->user()->restrict($r->input('assemblyNo'));
        if (!$assembly) return $this->index($r, t('roll.error.assembly'));
        if (!$file || strtolower($file->getClientOriginalExtension()) !== 'xlsx') return $this->index($r, t('import.error.file'));
        if (RollUpdate::where('assembly_no', $assembly)->where('status', 'PREVIEW')->exists()) return $this->index($r, t('roll.error.pending'));
        try {
            $u = $this->rolls->stage($file->getRealPath(), $file->getClientOriginalName(), $assembly, $this->user()->username());
        } catch (\Throwable $e) {
            return $this->index($r, t('import.error.read') . ' ' . $e->getMessage());
        }
        $a = $u->countsData()['actions'] ?? [];
        $this->audit()->log('COMPARE', 'ROLL', $u->id, "AC $assembly, {$u->file_name}: " . collect($a)->map(fn ($n, $k) => "$k $n")->implode(', '));
        return redirect('/roll-update/' . $u->id);
    }

    public function show(Request $r, int $id)
    {
        $u = $this->find($id);
        $action = in_array($r->query('action'), RollUpdateService::ACTIONS, true) ? $r->query('action') : null;
        $booth = is_numeric($r->query('boothNo')) ? (int) $r->query('boothNo') : null;
        $survey = $r->query('survey') === '1';
        $rows = DB::table('roll_update_rows')->where('roll_update_id', $u->id)
            ->when($action, fn ($q) => $q->where('action', $action), fn ($q) => $q->where('action', '<>', 'SAME'))
            ->when($booth !== null, fn ($q) => $q->where(fn ($w) => $w->where('booth_no', $booth)->orWhere('old_booth_no', $booth)))
            ->when($survey, fn ($q) => $q->where('has_survey', true))
            ->orderBy('booth_no')->orderBy('serial_no')->orderBy('id')->paginate(100)->withQueryString();
        return view('rollupdate.show', ['u' => $u, 'c' => $u->countsData(), 'rows' => $rows, 'action' => $action, 'booth' => $booth, 'survey' => $survey,
            'canUndo' => $this->rolls->canUndo($u), 'result' => session('rollResult')]);
    }

    public function apply(int $id)
    {
        $u = $this->find($id);
        if ($b = $this->blocked()) return redirect('/roll-update/' . $id)->with('error', 'roll.error.area');
        if ($u->status !== 'PREVIEW') return redirect('/roll-update/' . $id);
        $res = $this->rolls->apply($u, $this->user()->username());
        $this->audit()->log('APPLY', 'ROLL', $u->id, "AC {$u->assembly_no}, {$u->file_name}: {$res['updated']} updated, {$res['added']} added, {$res['deleted']} marked deleted"
            . ($res['failed'] ? ', ' . count($res['failed']) . ' failed' : ''));
        return redirect('/roll-update/' . $id)->with('rollResult', $res);
    }

    public function undo(int $id)
    {
        $u = $this->find($id);
        if (!$this->rolls->canUndo($u)) return redirect('/roll-update/' . $id)->with('error', 'roll.error.undo');
        $res = $this->rolls->undo($u);
        $this->audit()->log('UNDO', 'ROLL', $u->id, "AC {$u->assembly_no}, {$u->file_name}: {$res['restored']} restored, {$res['removed']} removed");
        return redirect('/roll-update/' . $id)->with('success', 'roll.undone');
    }

    public function discard(int $id)
    {
        $u = $this->find($id);
        if ($u->status === 'PREVIEW') {
            $this->rolls->discard($u);
            $this->audit()->log('DISCARD', 'ROLL', $u->id, "AC {$u->assembly_no}, {$u->file_name}");
        }
        return redirect('/roll-update')->with('success', 'roll.discarded');
    }

    public function report(int $id)
    {
        $u = $this->find($id);
        $file = $this->rolls->reportFile($u);
        return response()->download($file, 'roll-update-' . $u->assembly_no . '-' . $u->id . '.xlsx', ['Content-Type' => self::XLSX])->deleteFileAfterSend();
    }
}
