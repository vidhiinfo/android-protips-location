<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Services\Export\ExcelExporter;
use App\Services\Export\PdfExporter;
use App\Services\Reports\Report;
use App\Services\Results\Form20Importer;
use App\Services\Results\PlannerService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;

/** Results > Votes-to-win planner: votes needed per booth from last election's result and the survey. */
class PlannerController extends Controller
{
    public function __construct(private PlannerService $planner) {}

    private static function num(Request $r, string $k): ?float
    {
        $v = trim((string) $r->query($k, ''));
        return $v !== '' && is_numeric($v) ? (float) $v : null;
    }

    /** Assembly, ward, past election, party and the what-if numbers from the query string. */
    private function inputs(Request $r, VoterService $v): array
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($v->assemblies());
        $w = is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null;
        $pasts = $a ? $this->planner->pastElections((int) $a) : [];
        $past = null;
        if ($r->query('electionId') === '0') $past = null;   // "none": plan from the survey only
        elseif ($r->query('electionId')) $past = collect($pasts)->firstWhere('id', (int) $r->query('electionId'));
        else $past = $pasts[0] ?? null;
        $party = trim((string) $r->query('party', '')) ?: (string) config('voters.planner.our_party', '');
        $opt = ['margin' => self::num($r, 'margin'), 'supporter' => self::num($r, 'supporter'), 'turnout' => self::num($r, 'turnout'), 'target' => self::num($r, 'target')];
        $opt = array_filter($opt, fn ($x) => $x !== null);
        if (isset($opt['margin'])) $opt['margin'] = max(0, min(30, $opt['margin']));
        if (isset($opt['supporter'])) $opt['supporter'] = max(10, min(100, $opt['supporter']));
        return [$a ? (int) $a : null, $w, $pasts, $past, strtoupper($party), $opt];
    }

    public function index(Request $r, VoterService $v)
    {
        [$a, $w, $pasts, $past, $party, $opt] = $this->inputs($r, $v);
        $plan = $a ? $this->planner->plan($a, $past, $party ?: null, $opt, $w) : null;
        $q = array_filter(['assemblyNo' => $a, 'wardNo' => $w, 'electionId' => $r->query('electionId'), 'party' => $r->query('party'),
            'margin' => $r->query('margin'), 'supporter' => $r->query('supporter'), 'turnout' => $r->query('turnout'), 'target' => $r->query('target'),
            'status' => $r->query('status')], fn ($x) => $x !== null && $x !== '');
        $status = in_array($r->query('status'), PlannerService::STATUSES, true) ? $r->query('status') : null;
        return view('results.planner', ['assemblyNo' => $a, 'wardNo' => $w, 'assemblies' => $v->assemblies(), 'wards' => $a ? $v->wards($a) : [],
            'pasts' => $pasts, 'past' => $past, 'parties' => $a ? $this->planner->parties($past, $a) : [], 'plan' => $plan, 'status' => $status,
            'query' => $q, 'elections' => Election::orderByDesc('election_year')->orderBy('name')->get()]);
    }

    public function export(Request $r, string $format, VoterService $v, ExcelExporter $x, PdfExporter $p)
    {
        [$a, $w, , $past, $party, $opt] = $this->inputs($r, $v);
        abort_if($a === null, 404);
        $plan = $this->planner->plan($a, $past, $party ?: null, $opt, $w);
        $rep = (new Report(t('plan.title') . ' - ' . t('col.assemblyNo') . " $a " . $plan['assembly_name'] . ($w !== null ? ' - ' . t('col.wardNo') . " $w" : ''),
            [t('col.boothNo'), t('col.boothName'), t('plan.status'), t('plan.electors'), t('plan.turnout'), t('plan.expected'), t('plan.pastMargin'),
                t('plan.target'), t('plan.supporters'), t('plan.neutral'), t('plan.opposition'), t('plan.notSurveyed'), t('plan.projected'), t('plan.gap'),
                t('plan.reachable'), t('plan.action')]))
            ->subtitle(self::summaryLine($plan));
        foreach ($plan['rows'] as $b) $rep->add([$b['booth_no'], $b['booth_name'], t('plan.st.' . $b['status']), $b['electors'], round($b['turnout'], 1), $b['expected'],
            $b['past_margin'], $b['target'], $b['S'], $b['N'], $b['O'], $b['U'], $b['projected'], $b['gap'], $b['reachable'], self::action($b)]);
        $s = $plan['sum'];
        $rep->totals = ['', t('report.total'), '', $s['electors'], round($plan['turnout'], 1), $s['expected'], $plan['past'] ? $plan['past']['margin'] : null,
            $s['target'], $s['S'], $s['N'], $s['O'], $s['U'], $s['projected'], $plan['seat_gap'], $s['reachable'], ''];
        $rep->note = t('plan.how');
        return $format === 'pdf' ? $this->download($p->report($rep), "votes-to-win-$a.pdf", 'application/pdf')
            : $this->download($x->report($rep), "votes-to-win-$a.xlsx", self::XLSX);
    }

    public static function summaryLine(array $plan): string
    {
        $s = $plan['sum'];
        return t('plan.winNumber') . ': ' . $s['target'] . ' | ' . t('plan.projected') . ': ' . $s['projected'] . ' | ' . t('plan.gap') . ': ' . $plan['seat_gap']
            . ' | ' . t('plan.targetShare') . ': ' . number_format($plan['target_share'], 1) . '%'
            . ($plan['past'] ? ' | ' . t('plan.swingNeeded') . ': ' . sprintf('%+.1f', $plan['swing']) . ' ' . t('plan.points') : '')
            . ' | ' . t('plan.party') . ': ' . ($plan['party'] ?: '-');
    }

    /** What to do in this booth, in one line. */
    public static function action(array $b): string
    {
        return match ($b['status']) {
            'swing' => $b['U'] > 0 ? t('plan.do.swing', $b['gap'], $b['N'], $b['U']) : t('plan.do.swingN', $b['gap'], $b['N']),
            'tough' => t('plan.do.tough', $b['S']),
            'track' => t('plan.do.track', $b['S']),
            default => t('plan.do.safe'),
        };
    }

    public function import(Request $r, Form20Importer $imp)
    {
        $r->validate(['file' => 'required|file|max:10240', 'assemblyNo' => 'required|integer']);
        $a = (int) $r->input('assemblyNo');
        abort_unless($this->user()->allows($a), 403);
        $ext = strtolower($r->file('file')->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'csv'], true)) return back()->with('error', t('form20.badFile'));
        if ($r->input('electionId') === 'new') {
            $r->validate(['newName' => 'required|string|max:150', 'newYear' => 'required|integer|min:1951|max:2100']);
            $e = Election::create(['name' => trim($r->input('newName')), 'type' => in_array($r->input('newType'), Election::TYPES, true) ? $r->input('newType') : 'ASSEMBLY',
                'election_year' => (int) $r->input('newYear'), 'current' => false]);
        } else {
            $e = Election::findOrFail((int) $r->input('electionId'));
        }
        $res = $imp->import($r->file('file')->getRealPath(), $ext, $e, $a, $this->user()->username());
        $back = '/planner?' . http_build_query(['assemblyNo' => $a, 'electionId' => $e->id]);
        if ($res['errors']) return redirect($back)->with('error', implode(' ', array_map(fn ($k) => t($k), $res['errors'])));
        return redirect($back)->with('success', t('form20.done', $res['booths'], count($res['matched']) + count($res['added']), count($res['added']))
            . ($res['skipped'] ? ' ' . t('form20.skipped', implode(', ', array_slice($res['skipped'], 0, 8))) : ''));
    }

    /** A blank Form 20 sheet: booth numbers of this seat, one column per candidate already entered (or examples). */
    public function template(Request $r, VoterService $v, ExcelExporter $x)
    {
        [$a, , , $past] = $this->inputs($r, $v);
        abort_if($a === null, 404);
        $heads = [t('col.boothNo')];
        foreach ($past ? $this->planner->parties($past, $a) : [] as $short => $name) $heads[] = $short;
        if (count($heads) === 1) $heads = array_merge($heads, ['Candidate A (PARTY1)', 'Candidate B (PARTY2)', 'NOTA']);
        $rep = new Report('Form 20 - ' . t('col.assemblyNo') . " $a", $heads);
        foreach (\App\Models\Booth::where('assembly_no', $a)->orderBy('booth_no')->pluck('booth_no') as $b) $rep->add(array_merge([$b], array_fill(0, count($heads) - 1, null)));
        return $this->download($x->report($rep), "form20-$a.xlsx", self::XLSX);
    }
}
