<?php

namespace App\Http\Controllers;

use App\Models\Assembly;
use App\Models\Booth;
use App\Models\Voter;
use App\Services\Export\ExcelExporter;
use App\Services\Export\ExcelImporter;
use App\Services\Export\PdfExporter;
use App\Services\Messaging\MessagingService;
use App\Services\Voters\VoterFilter;
use App\Services\Voters\VoterService;
use App\Support\Text;
use App\Support\Validation;
use Illuminate\Http\Request;

class VoterController extends Controller
{
    public function __construct(private VoterService $voters) {}

    private function filter(Request $r): VoterFilter
    {
        $f = VoterFilter::fromRequest($r);
        $f->set('assemblyNo', $this->user()->restrict($f->get('assemblyNo')));
        if ($f->get('wardNo') && $f->get('boothNo') && !in_array($f->get('boothNo'), $this->voters->boothsByWard($f->get('assemblyNo'))[$f->get('wardNo')] ?? [])) {
            $f->set('boothNo', null);   // booth chosen earlier is not in the newly chosen ward
        }
        return $f;
    }

    private function load(int $id): Voter
    {
        $v = Voter::find($id);
        abort_if(!$v || !$this->user()->allows($v->assembly_no), 404, t('error.notFound'));
        return $v;
    }

    public function index(Request $r)
    {
        $f = $this->filter($r);
        $page = $this->voters->search($f);
        $a = $f->get('assemblyNo');
        return view('voters.list', ['f' => $f, 'page' => $page, 'assemblies' => $this->voters->assemblies(), 'booths' => $this->voters->booths($a),
            'wards' => $this->voters->wards($a), 'wardMap' => $this->voters->boothsByWard($a),
            'districts' => $this->voters->districts($a), 'villages' => $this->voters->villages($a),
            'castes' => $this->voters->distinctValues('caste', $a), 'politicalStatuses' => $this->voters->distinctValues('political_status', $a),
            'total' => $this->voters->total($this->user()->workAssembly()), 'genderCounts' => $this->voters->genderCounts($this->user()->workAssembly())]);
    }

    public function show(int $voter, MessagingService $m)
    {
        $v = $this->load($voter);
        return view('voters.view', ['voter' => $v, 'household' => $this->voters->household($v), 'optedOut' => $m->isOptedOut($v->mobile, 'SMS')]);
    }

    public function household(Request $r, int $voter)
    {
        $v = $this->load($voter);
        return view($r->query('fragment') ? 'voters.household-content' : 'voters.household', ['voter' => $v, 'household' => $this->voters->household($v)]);
    }

    private function formData(Voter $v): array
    {
        return ['voter' => $v, 'masterAssemblies' => Assembly::orderBy('assembly_no')->get(),
            'masterBooths' => Booth::when($this->user()->scoped(), fn ($q) => $q->where('assembly_no', $this->user()->workAssembly()))->orderBy('assembly_no')->orderBy('booth_no')->get()];
    }

    public function create()
    {
        return view('voters.form', $this->formData(new Voter(['assembly_no' => $this->user()->workAssembly(), 'relation_type' => 'FATHER'])));
    }

    public function edit(int $voter) { return view('voters.form', $this->formData($this->load($voter))); }

    public function store(Request $r) { return $this->save($r, new Voter(), 'flash.added'); }
    public function update(Request $r, int $voter) { return $this->save($r, $this->load($voter), 'flash.updated'); }

    private function save(Request $r, Voter $v, string $flash)
    {
        $data = Validation::clean($r->only(array_keys(VoterService::rules())));
        if ($this->user()->scoped()) $data['assembly_no'] = $this->user()->workAssembly();
        $validator = validator($data, VoterService::rules(), Validation::messages());
        $epic = strtoupper((string) Text::asciiDigits((string) ($data['epic_no'] ?? '')));
        $validator->after(function ($val) use ($epic, $v) {
            if ($epic !== '' && Voter::where('epic_no', $epic)->when($v->exists, fn ($q) => $q->where('id', '<>', $v->id))->exists()) {
                $val->errors()->add('epic_no', t('validation.epic.duplicate'));
            }
        });
        if ($validator->fails()) {
            // invalid dates cannot go into the model's date casts: show them back as typed
            $bad = array_intersect_key($data, array_flip(array_keys(array_filter(Voter::PROFILE, fn ($d) => $d[1] === 'date'))), $validator->errors()->messages());
            $v->fill(array_diff_key($data, $bad));
            return response()->view('voters.form', $this->formData($v) + ['errors' => $validator->errors(), 'rawDates' => $bad], 200);
        }
        $v->fill($data);
        $this->voters->save($v);
        return redirect('/voters/' . $v->id)->with('success', $flash);
    }

    public function destroy(int $voter)
    {
        $this->voters->delete($this->load($voter));
        return redirect('/voters')->with('success', 'flash.deleted');
    }

    // ---------------------------------------------------------------- export / print
    private function title(VoterFilter $f): string
    {
        $t = t('list.title');
        if ($a = $f->get('assemblyNo')) {
            $name = $this->voters->assemblies()[$a] ?? '';
            $t .= ' - ' . t('col.assemblyNo') . " $a" . ($name ? " ($name)" : '');
        }
        if ($w = $f->get('wardNo')) $t .= ' - ' . t('col.wardNo') . " $w";
        if ($b = $f->get('boothNo')) $t .= ' - ' . t('col.boothNo') . " $b";
        return $t;
    }

    public static function summary(VoterFilter $f): string
    {
        $s = [];
        if ($w = $f->get('wardNo')) $s[] = t('col.wardNo') . ': ' . $w;
        foreach (['q' => 'filter.search', 'name' => 'col.name', 'relativeName' => 'col.relativeName', 'epicNo' => 'col.epicNo', 'mobile' => 'col.mobile',
                     'houseNo' => 'col.houseNo', 'district' => 'col.district', 'villageWard' => 'col.villageWard', 'caste' => 'col.caste',
                     'politicalStatus' => 'col.politicalStatus', 'beneficiary' => 'filter.beneficiary', 'membershipNo' => 'col.membershipNo'] as $k => $label) {
            if ($v = $f->get($k)) $s[] = t($label) . ': ' . $v;
        }
        if ($g = $f->get('gender')) $s[] = t('col.gender') . ': ' . t('gender.' . $g);
        if ($f->get('ageMin') !== null || $f->get('ageMax') !== null) $s[] = t('col.age') . ': ' . ($f->get('ageMin') ?? 18) . '-' . ($f->get('ageMax') ?? '+');
        if ($m = $f->get('hasMobile')) $s[] = t($m === 'true' ? 'filter.withMobile' : 'filter.withoutMobile');
        if ($d = $f->get('deleted')) $s[] = t('filter.deleted.' . $d);
        return implode(', ', $s);
    }

    public function exportExcel(Request $r, ExcelExporter $x)
    {
        $f = $this->filter($r);
        @set_time_limit(600);
        $file = $x->votersFile($this->voters->searchLazy($f), $this->title($f));
        return response()->download($file, 'voters-' . date('Y-m-d') . '.xlsx', ['Content-Type' => self::XLSX])->deleteFileAfterSend();
    }

    public function exportPdf(Request $r, PdfExporter $p)
    {
        $f = $this->filter($r);
        if ($this->voters->count($f) > VoterService::maxPdfRows()) {
            return redirect('/voters?' . $f->queryWithSort())->with('error', 'export.pdf.tooMany');
        }
        return $this->download($p->voters($this->voters->searchAll($f, VoterService::maxPdfRows()), $this->title($f), self::summary($f)), 'voters-' . date('Y-m-d') . '.pdf', 'application/pdf');
    }

    public function print(Request $r)
    {
        $f = $this->filter($r);
        if ($this->voters->count($f) > VoterService::maxPdfRows()) {
            return redirect('/voters?' . $f->queryWithSort())->with('error', 'export.pdf.tooMany');
        }
        return view('voters.print', ['f' => $f, 'voters' => $this->voters->searchAll($f, VoterService::maxPdfRows()), 'title' => $this->title($f), 'summary' => self::summary($f),
            'autoprint' => $r->has('autoprint')]);
    }

    // ---------------------------------------------------------------- import
    public function importForm() { return view('voters.import', $this->importData()); }

    private function importData(): array
    {
        return ['assemblies' => $this->voters->assemblies(), 'defaultAssembly' => $this->user()->workAssembly()];
    }

    public function template(ExcelExporter $x) { return $this->download($x->template(), 'voter-import-template.xlsx', self::XLSX); }

    public function import(Request $r, ExcelImporter $imp)
    {
        $file = $r->file('file');
        if (!$file || strtolower($file->getClientOriginalExtension()) !== 'xlsx') return view('voters.import', $this->importData() + ['error' => t('import.error.file')]);
        try {
            $default = is_numeric($r->input('defaultAssembly')) ? (int) $r->input('defaultAssembly') : null;
            $res = $imp->import($file->getRealPath(), $this->user()->workAssembly(), $default);
        } catch (\Throwable $e) {
            return view('voters.import', $this->importData() + ['error' => t('import.error.read') . ' ' . $e->getMessage()]);
        }
        $this->audit()->log('IMPORT', 'VOTER', null, $file->getClientOriginalName() . ": {$res['inserted']} added, {$res['updated']} updated, " . count($res['errors']) . ' errors');
        return view('voters.import', $this->importData() + ['result' => $res]);
    }
}
