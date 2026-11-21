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
        if (($w = $this->user()->workerId()) !== null) $f->set('workerId', $w);   // a worker's login: only their own voters
        if ($f->get('wardNo') && $f->get('boothNo') && !in_array($f->get('boothNo'), $this->voters->boothsByWard($f->get('assemblyNo'))[$f->get('wardNo')] ?? [])) {
            $f->set('boothNo', null);   // booth chosen earlier is not in the newly chosen ward
        }
        return $f;
    }

    private function load(int $id): Voter
    {
        $v = Voter::find($id);
        abort_if(!$v || !$this->user()->allowsVoter($v), 404, t('error.notFound'));
        return $v;
    }

    public function index(Request $r)
    {
        $f = $this->filter($r);
        try { $page = $this->voters->search($f); $slow = false; }
        catch (\App\Services\Voters\SearchTooSlow $e) {   // too broad for the time limit: empty result with advice
            $page = new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, $f->size, 1, ['path' => $r->url()]);
            $slow = true;
        }
        $a = $f->get('assemblyNo');
        return view('voters.list', ['f' => $f, 'page' => $page, 'searchSlow' => $slow, 'assemblies' => $this->voters->assemblies(), 'booths' => $this->voters->booths($a),
            'wards' => $this->voters->wards($a), 'wardMap' => $this->voters->boothsByWard($a),
            'districts' => $this->voters->districts($a), 'villages' => $this->voters->villages($a),
            // Caste / Sub Caste filter: the masters, plus values on voters that are not in a master
            'castes' => $castes = array_values(array_unique(array_merge(array_keys($tree = \App\Models\Caste::tree()), $this->voters->distinctValues('caste', $a)))),
            'subCastes' => array_values(array_unique(array_merge(...array_values($tree ?: [[]]), ...[$this->voters->distinctValues('sub_caste', $a)]))),
            'casteTreeAll' => $tree, 'politicalStatuses' => $this->voters->distinctValues('political_status', $a),
            'statusFilter' => ['politicalStatus' => \App\Support\StatusMasters::options('political_status', $this->voters->distinctValues('political_status', $a)),
                'socialStatus' => \App\Support\StatusMasters::options('social_status', $this->voters->distinctValues('social_status', $a)),
                'orgStatus' => \App\Support\StatusMasters::options('organisational_status', $this->voters->distinctValues('organisational_status', $a)),
                'beneficiary' => \App\Support\StatusMasters::options('beneficiary', array_merge($this->voters->distinctValues('beneficiary1', $a), $this->voters->distinctValues('beneficiary2', $a), $this->voters->distinctValues('beneficiary3', $a)))],
            'workers' => \App\Models\Worker::where('active', true)->when($a, fn ($q) => $q->where('assembly_no', $a))->orderBy('booth_no')->orderBy('name')->get(),
            'total' => $this->voters->total($this->user()->workAssembly()), 'genderCounts' => $this->voters->genderCounts($this->user()->workAssembly())]);
    }

    public function show(int $voter, MessagingService $m)
    {
        $v = $this->load($voter);
        return view('voters.view', ['voter' => $v, 'household' => $this->voters->household($v), 'optedOut' => $m->isOptedOut($v->mobile, 'SMS'),
            'casteTree' => \App\Models\Caste::tree()]);
    }

    public function household(Request $r, int $voter)
    {
        $v = $this->load($voter);
        return view($r->query('fragment') ? 'voters.household-content' : 'voters.household', ['voter' => $v, 'household' => $this->voters->household($v),
            'casteTree' => \App\Models\Caste::tree()]);
    }

    /** One caste / sub caste for every voter of this house (same house no. + address + ward). */
    public function householdCaste(Request $r, int $voter)
    {
        $v = $this->load($voter);
        $caste = trim((string) $r->input('caste')); $sub = trim((string) $r->input('sub_caste')) ?: null;
        if ($caste === '' || mb_strlen($caste) > 100 || ($sub !== null && mb_strlen($sub) > 100)) return redirect('/voters/' . $v->id)->with('error', 'validation.required');
        $tree = \App\Models\Caste::tree();
        if ($tree && $sub !== null && !in_array($sub, $tree[$caste] ?? [], true) && $sub !== $v->sub_caste) $sub = null;   // sub caste must belong to the caste
        $category = \App\Models\Caste::where('name', $caste)->value('category');
        $n = 0;
        foreach ($this->voters->household($v) as $h) {
            if (!$this->user()->allowsVoter($h)) continue;
            $h->caste = $caste; $h->sub_caste = $sub;
            if (!$h->category && $category) $h->category = $category;
            $h->save(); $n++;
        }
        $this->audit()->log('UPDATE', 'VOTER', $v->id, "caste '$caste'" . ($sub ? " / '$sub'" : '') . " for $n voters of house {$v->house_no}");
        return redirect('/voters/' . $v->id)->with('success', t('household.casteApplied', $n));
    }

    /** Fields that can be set for many selected voters at once (after the field team has confirmed the answers). */
    public const BULK_FIELDS = ['caste', 'sub_caste', 'political_status', 'social_status', 'organisational_status', 'place_of_origin'];
    public const BULK_MAX = 1000;

    /** Sets the filled-in fields for the ticked voters; empty fields are left as they are, "clear" empties them. */
    public function bulkUpdate(Request $r)
    {
        $ids = array_slice(array_values(array_unique(array_map('intval', (array) $r->input('ids', [])))), 0, self::BULK_MAX);
        $back = '/voters' . ($r->input('back') && str_starts_with((string) $r->input('back'), '?') ? $r->input('back') : '');
        $set = [];
        foreach (self::BULK_FIELDS as $f) {
            $val = trim((string) $r->input($f));
            if ($val === '') continue;
            if (mb_strlen($val) > 100) return redirect($back)->with('error', 'validation.required');
            $set[$f] = $val === '-' ? null : (isset(\App\Support\StatusMasters::COLUMNS[$f]) ? \App\Support\StatusMasters::normalize($f, $val) : $val);   // "-" clears the field
        }
        $wid = trim((string) $r->input('worker_id'));
        if ($wid !== '' && $this->user()->can('WORKER_MANAGE')) {
            $worker = $wid === '-' ? null : \App\Models\Worker::find((int) $wid);
            if ($wid === '-' || ($worker && $this->user()->allows($worker->assembly_no))) $set['worker_id'] = $worker?->id;
        }
        if (!$ids || !$set) return redirect($back)->with('error', 'bulk.nothing');
        $tree = \App\Models\Caste::tree();
        if (isset($set['sub_caste']) && $set['sub_caste'] !== null && $tree && isset($set['caste']) && !in_array($set['sub_caste'], $tree[$set['caste']] ?? [], true)) unset($set['sub_caste']);
        $category = isset($set['caste']) ? \App\Models\Caste::where('name', $set['caste'])->value('category') : null;
        $n = 0;
        // every voter's previous values are kept, so the whole bulk update can be undone (Voters > Bulk changes)
        $track = \Illuminate\Support\Facades\Schema::hasTable('bulk_changes');
        \Illuminate\Support\Facades\DB::transaction(function () use ($ids, $set, $category, $track, &$n, &$changeId) {
            $changeId = $track ? \Illuminate\Support\Facades\DB::table('bulk_changes')->insertGetId(['created_by' => $this->user()->username(), 'created_at' => now(),
                'assembly_no' => $this->user()->workAssembly(), 'fields' => json_encode($set, JSON_UNESCAPED_UNICODE), 'voter_count' => 0, 'status' => 'APPLIED']) : null;
            $rows = [];
            foreach (Voter::whereIn('id', $ids)->get() as $v) {
                if (!$this->user()->allowsVoter($v)) continue;
                $keys = array_keys($set);
                if (array_key_exists('caste', $set) && $set['caste'] === null) $keys[] = 'sub_caste';
                if ($category && !$v->category) $keys[] = 'category';
                $before = []; foreach (array_unique($keys) as $k) $before[$k] = $v->getRawOriginal($k);
                foreach ($set as $f => $val) $v->$f = $val;
                if (array_key_exists('caste', $set) && $set['caste'] === null) $v->sub_caste = null;
                if ($category && !$v->category) $v->category = $category;
                $v->save(); $n++;
                $after = []; foreach (array_keys($before) as $k) $after[$k] = $v->getRawOriginal($k);
                if ($changeId) $rows[] = ['bulk_change_id' => $changeId, 'voter_id' => $v->id, 'before' => json_encode($before, JSON_UNESCAPED_UNICODE), 'after' => json_encode($after, JSON_UNESCAPED_UNICODE)];
                if (count($rows) >= 500) { \Illuminate\Support\Facades\DB::table('bulk_change_rows')->insert($rows); $rows = []; }
            }
            if ($rows) \Illuminate\Support\Facades\DB::table('bulk_change_rows')->insert($rows);
            if ($changeId) \Illuminate\Support\Facades\DB::table('bulk_changes')->where('id', $changeId)->update(['voter_count' => $n]);
        });
        $this->audit()->log('UPDATE', 'VOTER', $changeId, "bulk update of $n voters: " . implode(', ', array_map(fn ($f, $val) => "$f=" . ($val ?? '(cleared)'), array_keys($set), $set)));
        return redirect($back)->with('success', t('bulk.done', $n));
    }

    /** Booth-wise survey sheet: voters house by house with Caste / Sub Caste drop-downs, to fill in the field and upload back. */
    public function surveySheet(Request $r, ExcelExporter $x)
    {
        $f = $this->filter($r);
        if ($this->voters->count($f) > VoterService::maxPdfRows()) return redirect('/voters?' . $f->queryWithSort())->with('error', 'survey.tooMany');
        // houses in roll order (by the first serial no. of the house), members of a house together
        $all = $this->voters->searchAll($f, VoterService::maxPdfRows());
        $houseOf = fn ($v) => $v->assembly_no . '|' . $v->booth_no . '|' . mb_strtolower((string) $v->village_ward) . '|' . (\App\Support\Text::houseKey($v->house_no) ?: '#' . $v->id);
        $first = [];
        foreach ($all as $v) { $k = $houseOf($v); $first[$k] = min($first[$k] ?? PHP_INT_MAX, $v->booth_no * 100000 + (int) $v->serial_no); }
        $rows = $all->sortBy(fn ($v) => [$first[$houseOf($v)], $houseOf($v), (int) $v->serial_no])->values();
        return $this->download($x->surveySheet($rows, $this->title($f)), 'survey-' . date('Y-m-d') . '.xlsx', self::XLSX);
    }

    private function formData(Voter $v): array
    {
        return ['voter' => $v, 'casteTree' => \App\Models\Caste::tree(), 'masterAssemblies' => Assembly::orderBy('assembly_no')->get(),
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
        $validator->after(function ($val) use ($epic, $v, $data) {
            if (!\App\Support\Area::fits($data)) $val->errors()->add('booth_no', t('area.outside'));   // ward / booth / serial outside the user's area
            if ($epic !== '' && Voter::withoutGlobalScope('area')->where('epic_no', $epic)->when($v->exists, fn ($q) => $q->where('id', '<>', $v->id))->exists()) {
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
        // category from the Caste master when the voter has none
        if ($v->caste && !$v->category) $v->category = \App\Models\Caste::where('name', $v->caste)->value('category');
        if (!$v->caste) $v->sub_caste = null;
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
                     'houseNo' => 'col.houseNo', 'district' => 'col.district', 'villageWard' => 'col.villageWard', 'caste' => 'col.caste', 'subCaste' => 'col.subCaste',
                     'politicalStatus' => 'col.politicalStatus', 'socialStatus' => 'col.socialStatus', 'orgStatus' => 'col.organisationalStatus',
                     'sentiment' => 'filter.leaning', 'beneficiary' => 'filter.beneficiary', 'membershipNo' => 'col.membershipNo'] as $k => $label) {
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

    /** Voter slips for door-to-door distribution, in booth / serial order. */
    public function slips(Request $r, PdfExporter $p)
    {
        $f = $this->filter($r);
        if ($this->voters->count($f) > VoterService::maxPdfRows()) return redirect('/voters?' . $f->queryWithSort())->with('error', 'export.pdf.tooMany');
        $f->sort = 'boothNo';
        $rows = $f->apply(Voter::query())->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))
            ->orderBy('assembly_no')->orderBy('booth_no')->orderByRaw('serial_no is null')->orderBy('serial_no')->limit(VoterService::maxPdfRows())->get();
        $this->audit()->log('EXPORT', 'VOTER', null, 'voter slips: ' . count($rows) . ' voters');
        return $this->download($p->slips($rows, \App\Models\Election::where('current', true)->first()), 'voter-slips-' . date('Y-m-d') . '.pdf', 'application/pdf');
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
