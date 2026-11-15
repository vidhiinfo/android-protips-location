<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Party;
use App\Services\Export\ExcelExporter;
use App\Services\Export\PdfExporter;
use App\Services\Reports\ReportService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    private function check(string $id): void
    {
        abort_unless(in_array($id, ReportService::all(), true), 404);
        abort_if(in_array($id, ReportService::ADMIN_ONLY, true) && !$this->user()->can('REPORT_SENSITIVE'), 403);
    }

    private function build(Request $r, string $id)
    {
        $e = $r->query('electionId') ? Election::find($r->query('electionId')) : Election::where('current', true)->first();
        $this->reports->ward = is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null;
        return $this->reports->build($id, $this->user()->restrict($r->query('assemblyNo')), $this->booth($r),
            $e, $r->query('scope'), $r->query('party'));
    }

    /** Booth filter, dropped when it is not in the chosen ward. */
    private function booth(Request $r): ?int
    {
        $b = $r->query('boothNo') ? (int) $r->query('boothNo') : null;
        $w = is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null;
        if ($b && $w !== null && !in_array($b, app(VoterService::class)->boothsByWard($this->user()->restrict($r->query('assemblyNo')))[$w] ?? [])) return null;
        return $b;
    }

    public function index() { return view('reports.index', ['sections' => ReportService::SECTIONS, 'adminOnly' => ReportService::ADMIN_ONLY]); }

    public function view(Request $r, string $id, VoterService $voters)
    {
        $this->check($id);
        $a = $this->user()->restrict($r->query('assemblyNo'));
        $data = ['reportId' => $id, 'report' => $this->build($r, $id), 'assemblyNo' => $a, 'boothNo' => $this->booth($r),
            'wardNo' => $r->query('wardNo'), 'wards' => $voters->wards($a), 'wardMap' => $voters->boothsByWard($a),
            'electionId' => $r->query('electionId'), 'scope' => $r->query('scope'), 'party' => $r->query('party'),
            'assemblies' => $voters->assemblies(), 'booths' => $voters->booths($a), 'parties' => Party::orderBy('name')->get(),
            'elections' => Election::orderByDesc('election_year')->get(), 'autoprint' => $r->has('print')];
        return view($r->has('print') ? 'reports.print' : 'reports.view', $data);
    }

    public function export(Request $r, string $id, string $format, ExcelExporter $x, PdfExporter $p)
    {
        $this->check($id);
        $rep = $this->build($r, $id);
        $file = $id . '-' . date('Y-m-d');
        if ($format === 'pdf' && count($rep->rows) > \App\Services\Voters\VoterService::maxPdfRows()) {
            return redirect('/reports/' . $id . '?' . http_build_query($r->except('lang')))->with('error', 'export.pdf.tooMany');
        }
        return $format === 'pdf' ? $this->download($p->report($rep), "$file.pdf", 'application/pdf')
            : $this->download($x->report($rep), "$file.xlsx", self::XLSX);
    }
}
