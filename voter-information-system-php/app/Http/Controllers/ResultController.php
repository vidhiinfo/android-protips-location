<?php

namespace App\Http\Controllers;

use App\Models\Assembly;
use App\Models\Booth;
use App\Models\Election;
use App\Services\Export\ExcelExporter;
use App\Services\Export\PdfExporter;
use App\Services\Reports\Report;
use App\Services\Results\ResultService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private ResultService $results) {}

    private function common(?Election $e): array
    {
        $seats = [];
        if ($e) foreach ($this->results->seats($e) as $a) if ($this->user()->allows($a)) $seats[$a] = Assembly::where('assembly_no', $a)->value('name') ?? '';
        return ['elections' => Election::orderByDesc('election_year')->orderBy('name')->get(), 'election' => $e, 'seats' => $seats];
    }

    public function overview(Request $r)
    {
        $e = Election::currentOrLatest($r->query('electionId') ? (int) $r->query('electionId') : null);
        $contests = $e ? array_values(array_filter($this->results->electionContests($e), fn ($c) => $this->user()->allows($c['assembly_no']))) : [];
        return view('results.overview', $this->common($e) + ['contests' => $contests]);
    }

    public function contest(Request $r)
    {
        $e = Election::currentOrLatest($r->query('electionId') ? (int) $r->query('electionId') : null);
        if (!$e) return redirect('/results');
        $a = $this->user()->restrict($r->query('assemblyNo'));
        abort_if($a === null, 404);
        return view('results.contest', $this->common($e) + ['c' => $this->results->contest($e, $a)]);
    }

    public function exportContest(Request $r, string $format, ExcelExporter $x, PdfExporter $p)
    {
        $e = Election::findOrFail($r->query('electionId'));
        $a = $this->user()->restrict($r->query('assemblyNo'));
        $c = $this->results->contest($e, $a);
        $rep = (new Report(t('result.title') . ' - ' . t('col.assemblyNo') . " $a " . $c['assembly_name'],
            [t('result.rank'), t('result.candidate'), t('result.party'), t('result.votes'), t('result.voteSharePct')]))
            ->subtitle($e->name . ' | ' . t('result.turnoutPct') . ': ' . number_format($c['turnout'], 2) . ' | ' . t('result.margin') . ': ' . $c['margin']);
        foreach ($c['totals'] as $t) $rep->add([$t['rank'], $t['candidate']->name . ($t['candidate']->name_local ? ' / ' . $t['candidate']->name_local : ''),
            $t['candidate']->partyShort(), $t['votes'], round($t['share'], 2)]);
        $rep->totals = ['', t('report.total'), '', $c['total_votes'], $c['total_votes'] ? 100.0 : 0.0];
        return $format === 'pdf' ? $this->download($p->report($rep), "result-$a.pdf", 'application/pdf')
            : $this->download($x->report($rep), "result-$a.xlsx", self::XLSX);
    }

    public function entry(Request $r)
    {
        $e = Election::currentOrLatest($r->query('electionId') ? (int) $r->query('electionId') : null);
        $a = $this->user()->restrict($r->query('assemblyNo'));
        $b = $r->query('boothNo') ? (int) $r->query('boothNo') : null;
        $data = $this->common($e) + ['assemblyNo' => $a, 'boothNo' => $b, 'boothList' => null, 'entries' => null, 'reported' => []];
        if ($e && $a) {
            $data['boothList'] = Booth::where('assembly_no', $a)->orderBy('booth_no')->get();
            $data['reported'] = $this->results->reportedBooths($e->id, $a);
            if ($b) $data['entries'] = $this->results->boothEntry($e->id, $a, $b);
        }
        return view('results.entry', $data);
    }

    public function saveEntry(Request $r)
    {
        $e = (int) $r->input('electionId'); $a = (int) $r->input('assemblyNo'); $b = (int) $r->input('boothNo');
        abort_unless($this->user()->allows($a), 403);
        $this->results->saveBooth($e, $a, $b, (array) $r->input('votes', []), $this->user()->username());
        $next = Booth::where('assembly_no', $a)->where('booth_no', '>', $b)->orderBy('booth_no')->value('booth_no') ?? $b;
        return redirect("/results/entry?electionId=$e&assemblyNo=$a&boothNo=$next")->with('success', 'flash.saved');
    }
}
