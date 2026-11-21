<?php

namespace App\Http\Controllers;

use App\Models\Caste;
use App\Models\Voter;
use App\Services\Voters\VoterService;
use App\Support\FastValidator;
use App\Support\Text;
use Illuminate\Http\Request;

/**
 * Field survey on a phone: pick a booth, go house by house in roll order and record what each family member says
 * (mobile, caste, political status, schemes ...). Saves straight into the voter records.
 */
class FieldSurveyController extends Controller
{
    /** Fields a field worker fills for each member of the house. */
    public const FIELDS = ['mobile', 'alt_mobile', 'political_status', 'social_status', 'caste', 'sub_caste', 'beneficiary1', 'beneficiary2', 'beneficiary3',
        'organisational_status', 'dob', 'place_of_origin', 'membership_no', 'remarks'];
    public const STATUSES = ['Supporter', 'Karyakarta', 'Neutral', 'Opposition'];

    public function __construct(private VoterService $voters) {}

    /** Houses of a booth in roll order: [key => ['anchor' => first voter id, 'house' => house no, 'addr' => ..., 'n' => members, 'done' => surveyed members]]. */
    public function houses(int $a, int $b): array
    {
        $out = [];
        foreach (Voter::where('assembly_no', $a)->where('booth_no', $b)->when($this->user()->workerId(), fn ($q, $w) => $q->where('worker_id', $w))->orderByRaw('serial_no is null')->orderBy('serial_no')->orderBy('id')
                     ->get(['id', 'house_no', 'address', 'village_ward', 'political_status', 'caste', 'deleted']) as $v) {
            $k = Text::houseKey($v->house_no) === '' ? '#' . $v->id
                : Text::houseKey($v->house_no) . '|' . mb_strtolower(trim((string) $v->address)) . '|' . mb_strtolower(trim((string) $v->village_ward));
            $out[$k] ??= ['anchor' => $v->id, 'house' => $v->house_no, 'addr' => $v->address ?: $v->village_ward, 'n' => 0, 'done' => 0];
            if ($v->deleted) continue;
            $out[$k]['n']++;
            if ($v->political_status || $v->caste) $out[$k]['done']++;
        }
        return array_filter($out, fn ($h) => $h['n'] > 0);
    }

    public function index(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $w = is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null;
        $booths = $a ? $this->voters->booths($a, $w) : [];
        $b = $r->query('boothNo') && in_array((int) $r->query('boothNo'), $booths) ? (int) $r->query('boothNo') : null;
        $houses = $a && $b ? $this->houses($a, $b) : [];
        $q = trim((string) $r->query('house'));
        if ($q !== '') $houses = array_filter($houses, fn ($h) => str_contains(Text::houseKey($h['house']), Text::houseKey($q)));
        if ($r->query('pending')) $houses = array_filter($houses, fn ($h) => $h['done'] < $h['n']);
        return view('survey.index', ['assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'wards' => $a ? $this->voters->wards($a) : [],
            'wardNo' => $w, 'booths' => $booths, 'boothNo' => $b, 'houses' => $houses, 'house' => $q, 'pending' => (bool) $r->query('pending'),
            'stats' => $b ? $this->stats($a, $b) : null]);
    }

    private function stats(int $a, int $b): array
    {
        $all = $this->houses($a, $b);
        return ['houses' => count($all), 'housesDone' => count(array_filter($all, fn ($h) => $h['done'] >= $h['n'])),
            'voters' => array_sum(array_column($all, 'n')), 'votersDone' => array_sum(array_column($all, 'done'))];
    }

    private function load(int $id): Voter
    {
        $v = Voter::find($id);
        abort_if(!$v || !$this->user()->allowsVoter($v), 404, t('error.notFound'));
        return $v;
    }

    public function house(int $voter)
    {
        $v = $this->load($voter);
        $members = $this->voters->household($v)->filter(fn ($m) => $this->user()->allowsVoter($m))->values();
        $keys = array_keys($this->houses($v->assembly_no, $v->booth_no));
        $anchors = array_column($this->houses($v->assembly_no, $v->booth_no), 'anchor');
        $i = array_search($members->first()->id ?? $v->id, $anchors);
        if ($i === false) foreach ($members as $m) if (($i = array_search($m->id, $anchors)) !== false) break;
        return view('survey.house', ['v' => $v, 'members' => $members, 'casteTree' => Caste::tree(),
            'statusOpts' => array_map(fn ($c) => \App\Support\StatusMasters::options($c), ['political_status' => 'political_status', 'social_status' => 'social_status',
                'organisational_status' => 'organisational_status', 'beneficiary' => 'beneficiary']),
            'quick' => \App\Support\StatusMasters::quickLeanings(),
            'statuses' => array_values(array_unique(array_merge(self::STATUSES, $this->voters->distinctValues('political_status', $v->assembly_no)))),
            'schemes' => array_values(array_unique(array_merge($this->voters->distinctValues('beneficiary1', $v->assembly_no), $this->voters->distinctValues('beneficiary2', $v->assembly_no)))),
            'prev' => $i !== false && $i > 0 ? $anchors[$i - 1] : null, 'next' => $i !== false && $i < count($anchors) - 1 ? $anchors[$i + 1] : null,
            'pos' => $i === false ? null : $i + 1, 'count' => count($keys)]);
    }

    public function save(Request $r, int $voter)
    {
        $v = $this->load($voter);
        $members = $this->voters->household($v)->keyBy('id');
        $rules = array_intersect_key(VoterService::rules(), array_flip(self::FIELDS));
        $fv = new FastValidator($rules, ['regex' => t('validation.mobile'), 'max' => t('validation.max'), 'date_format' => t('validation.date'), 'before_or_equal' => t('validation.date'), 'after' => t('validation.date')]);
        $tree = Caste::tree();
        $n = 0;
        foreach ((array) $r->input('m', []) as $id => $data) {
            $m = $members[(int) $id] ?? null;
            if (!$m || !$this->user()->allowsVoter($m) || !is_array($data)) continue;
            $row = [];
            foreach (self::FIELDS as $f) $row[$f] = isset($data[$f]) && trim((string) $data[$f]) !== '' ? trim((string) $data[$f]) : null;
            foreach (array_keys(\App\Support\StatusMasters::COLUMNS) as $sf) if (array_key_exists($sf, $row)) $row[$sf] = \App\Support\StatusMasters::normalize($sf, $row[$sf]);
            if ($row['alt_mobile']) $row['alt_mobile'] = preg_replace('/^(\+?91|0)/', '', preg_replace('/\D/', '', $row['alt_mobile']));
            if ($row['mobile']) $row['mobile'] = preg_replace('/^(\+?91|0)/', '', preg_replace('/\D/', '', $row['mobile']));
            if ($err = $fv->firstError($row)) {
                return redirect('/survey/house/' . $v->id)->withInput()->with('error', $m->name . ': ' . t('col.' . lcfirst(str_replace('_', '', ucwords($err[0], '_')))) . ' - ' . $err[1]);
            }
            if ($tree && $row['sub_caste'] && $row['caste'] && !in_array($row['sub_caste'], $tree[$row['caste']] ?? [], true) && $row['sub_caste'] !== $m->sub_caste) $row['sub_caste'] = null;
            $changed = false;
            foreach ($row as $f => $val) {
                $old = $f === 'dob' ? $m->dob?->format('Y-m-d') : $m->$f;
                if ((string) $old !== (string) $val) { $m->$f = $val; $changed = true; }
            }
            if ($row['caste'] && !$m->category && ($cat = Caste::where('name', $row['caste'])->value('category'))) $m->category = $cat;
            if ($changed) { $m->save(); $n++; }
            // consent asked at the door: recorded with its history (DPDP)
            if (array_key_exists('consent', $data) && \App\Services\Privacy\PrivacyService::enabled()) {
                $cv = in_array($data['consent'], ['Y', 'N'], true) ? $data['consent'] : null;
                if ($cv !== $m->consent_contact && ($cv !== null || $m->consent_contact !== null)) {
                    app(\App\Services\Privacy\PrivacyService::class)->setConsent($m, $cv, 'survey', null, $this->user()->username());
                    if (!$changed) $n++;
                }
            }
        }
        $this->audit()->log('UPDATE', 'VOTER', $v->id, "field survey: $n voters of house {$v->house_no}, booth {$v->booth_no}");
        $to = $r->input('go') === 'next' && $r->input('next') ? '/survey/house/' . (int) $r->input('next') : '/survey/house/' . $v->id;
        return redirect($to)->with('success', t('fs.saved', $n, $v->house_no ?: '-'));
    }
}
