<?php

namespace App\Http\Controllers;

use App\Models\{Assembly, Booth, BoothResult, Candidate, Caste, Election, OrgStatus, Party, PoliticalStatus, SocialStatus, SubCaste, Voter};
use App\Support\StatusMasters;
use App\Support\Validation;
use Illuminate\Http\Request;

/**
 * Masters: assemblies, booths, parties, elections, candidates. One controller and two generic views
 * (masters/list, masters/form) driven by the definitions below.
 */
class MasterController extends Controller
{
    private const ENTITY = ['assemblies' => 'ASSEMBLY', 'booths' => 'BOOTH', 'parties' => 'PARTY', 'elections' => 'ELECTION', 'candidates' => 'CANDIDATE',
        'castes' => 'CASTE', 'subcastes' => 'SUB_CASTE', 'socialstatuses' => 'SOCIAL_STATUS', 'politicalstatuses' => 'POLITICAL_STATUS', 'orgstatuses' => 'ORG_STATUS'];
    /** Status masters: master type => voter column whose values follow a rename. */
    public const STATUS = ['socialstatuses' => 'social_status', 'politicalstatuses' => 'political_status', 'orgstatuses' => 'organisational_status'];

    private function defs(): array
    {
        $assemblyOptions = fn () => Assembly::orderBy('assembly_no')->get()->mapWithKeys(fn ($a) => [$a->assembly_no => $a->assembly_no . ' - ' . $a->name])->all();
        return [
            'assemblies' => ['model' => Assembly::class, 'title' => 'master.assemblies', 'one' => 'master.assembly', 'order' => ['assembly_no'],
                'fields' => [
                    'assembly_no' => ['col.assemblyNo', 'number', ['required', 'integer', 'min:1']],
                    'name' => ['col.assemblyName', 'text', ['required', 'max:100']],
                    'name_local' => ['master.nameLocal', 'local', ['nullable', 'max:100']],
                    'district' => ['col.district', 'local', ['nullable', 'max:100']],
                    'state' => ['master.state', 'local', ['nullable', 'max:100']],
                    'reserved_for' => ['master.reservedFor', 'text', ['nullable', 'max:10']]],
                'unique' => [['assembly_no']], 'filter' => null],
            'booths' => ['model' => Booth::class, 'title' => 'master.booths', 'one' => 'master.booth', 'order' => ['assembly_no', 'booth_no'],
                'fields' => [
                    'assembly_no' => ['col.assemblyNo', 'select', ['required', 'integer', 'min:1'], $assemblyOptions],
                    'booth_no' => ['col.boothNo', 'number', ['required', 'integer', 'min:1']],
                    'name' => ['col.boothName', 'text', ['required', 'max:200']],
                    'name_local' => ['master.nameLocal', 'local', ['nullable', 'max:200']],
                    'village_ward' => ['col.villageWard', 'local', ['nullable', 'max:100']],
                    'location' => ['master.location', 'local', ['nullable', 'max:300']]],
                'unique' => [['assembly_no', 'booth_no']], 'filter' => 'assembly_no'],
            'parties' => ['model' => Party::class, 'title' => 'master.parties', 'one' => 'master.party', 'order' => ['name'],
                'fields' => [
                    'short_name' => ['master.shortName', 'text', ['required', 'max:20']],
                    'name' => ['master.partyName', 'text', ['required', 'max:150']],
                    'name_local' => ['master.nameLocal', 'local', ['nullable', 'max:150']],
                    'symbol' => ['master.symbol', 'local', ['nullable', 'max:100']],
                    'color' => ['master.color', 'color', ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']]],
                'unique' => [['short_name']], 'filter' => null, 'hint' => 'master.partyHint'],
            'elections' => ['model' => Election::class, 'title' => 'master.elections', 'one' => 'master.election', 'order' => ['election_year desc', 'name'],
                'fields' => [
                    'name' => ['master.electionName', 'local', ['required', 'max:150']],
                    'type' => ['master.electionType', 'select', ['required', 'in:' . implode(',', Election::TYPES)],
                        fn () => collect(Election::TYPES)->mapWithKeys(fn ($t) => [$t => t('election.type.' . $t)])->all()],
                    'election_year' => ['master.year', 'number', ['required', 'integer', 'between:1950,2100']],
                    'poll_date' => ['master.pollDate', 'date', ['nullable', 'date']],
                    'current' => ['master.currentHelp', 'checkbox', ['nullable']]],
                'unique' => [], 'filter' => null],
            'candidates' => ['model' => Candidate::class, 'title' => 'master.candidates', 'one' => 'master.candidate', 'order' => ['election_id desc', 'assembly_no', 'ballot_no'],
                'fields' => [
                    'election_id' => ['master.election', 'select', ['required', 'integer'], fn () => Election::orderByDesc('election_year')->pluck('name', 'id')->all()],
                    'assembly_no' => ['col.assemblyNo', 'select', ['required', 'integer', 'min:1'], $assemblyOptions],
                    'ballot_no' => ['master.ballotNo', 'number', ['nullable', 'integer', 'min:1']],
                    'name' => ['result.candidate', 'text', ['required', 'max:120']],
                    'name_local' => ['master.nameLocal', 'local', ['nullable', 'max:120']],
                    'party_id' => ['result.party', 'select', ['nullable', 'integer'],
                        fn () => ['' => t('master.independent')] + Party::orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->short_name . ' - ' . $p->name])->all()]],
                'unique' => [], 'filter' => 'assembly_no'],
            'castes' => ['model' => Caste::class, 'title' => 'master.castes', 'one' => 'master.caste', 'order' => ['name'],
                'fields' => [
                    'name' => ['master.casteName', 'text', ['required', 'max:100']],
                    'name_local' => ['master.nameLocal', 'local', ['nullable', 'max:100']],
                    'category' => ['col.category', 'select', ['nullable', 'in:' . implode(',', Caste::CATEGORIES)],
                        fn () => ['' => '--'] + array_combine(Caste::CATEGORIES, array_map(fn ($c) => t('report.cat.' . $c), Caste::CATEGORIES))],
                    'active' => ['master.active', 'checkbox', ['nullable']]],
                'unique' => [['name']], 'filter' => null, 'hint' => 'master.casteHint'],
            'subcastes' => ['model' => SubCaste::class, 'title' => 'master.subCastes', 'one' => 'master.subCaste', 'order' => ['caste_id', 'name'],
                'fields' => [
                    'caste_id' => ['master.caste', 'select', ['required', 'integer', 'exists:castes,id'], fn () => Caste::orderBy('name')->pluck('name', 'id')->all()],
                    'name' => ['master.subCasteName', 'text', ['required', 'max:100']],
                    'name_local' => ['master.nameLocal', 'local', ['nullable', 'max:100']],
                    'active' => ['master.active', 'checkbox', ['nullable']]],
                'unique' => [['caste_id', 'name']], 'filter' => null],
            'socialstatuses' => $this->statusDef(SocialStatus::class, 'master.socialStatuses', 'col.socialStatus', 'group_name', 'master.group', StatusMasters::SOCIAL_GROUPS, 'master.group.'),
            'politicalstatuses' => $this->statusDef(PoliticalStatus::class, 'master.politicalStatuses', 'col.politicalStatus', 'leaning', 'master.leaning', StatusMasters::LEANINGS, 'master.leaning.'),
            'orgstatuses' => $this->statusDef(OrgStatus::class, 'master.orgStatuses', 'col.organisationalStatus', 'level', 'master.level', StatusMasters::ORG_LEVELS, 'master.level.'),
        ];
    }

    private function statusDef(string $model, string $title, string $one, string $group, string $groupLabel, array $groups, string $prefix): array
    {
        return ['model' => $model, 'title' => $title, 'one' => $one, 'order' => ['sort_order', 'name'],
            'fields' => [
                'name' => ['master.statusName', 'text', ['required', 'max:100']],
                'name_local' => ['master.nameLocal', 'local', ['nullable', 'max:100']],
                $group => [$groupLabel, 'select', ['nullable', 'in:' . implode(',', $groups)], fn () => ['' => '--'] + array_combine($groups, array_map(fn ($g) => t($prefix . $g), $groups))],
                'sort_order' => ['master.sortOrder', 'number', ['nullable', 'integer', 'min:0', 'max:100000']],
                'active' => ['master.active', 'checkbox', ['nullable']]],
            'unique' => [['name']], 'filter' => null, 'hint' => 'master.statusHint', 'usage' => true];
    }

    private function def(string $type): array
    {
        $d = $this->defs()[$type] ?? null;
        abort_if(!$d, 404);
        return $d + ['type' => $type];
    }

    /** Display value of one cell in the list. */
    public static function display($item, string $field, array $def)
    {
        $v = $item->$field;
        return match (true) {
            $field === 'party_id' => $item->party?->short_name ?? 'IND',
            $field === 'election_id' => $item->election?->name,
            $field === 'type' => $v ? t('election.type.' . $v) : '',
            $field === 'current' => $v ? '✓' : '',
            $field === 'poll_date' => $v?->format('d-m-Y'),
            $field === 'caste_id' => $item->caste?->name,
            $field === 'active' => $v ? '✓' : '',
            $field === 'category' => $v ? t('report.cat.' . $v) : '',
            $field === 'leaning' => $v ? t('master.leaning.' . $v) : '',
            $field === 'group_name' => $v ? t('master.group.' . $v) : '',
            $field === 'level' => $v ? t('master.level.' . $v) : '',
            default => $v,
        };
    }

    public function index(Request $r, string $type)
    {
        $d = $this->def($type);
        $q = $d['model']::query();
        if ($type === 'candidates') $q->with(['party', 'election']);
        $filterValue = $d['filter'] ? $this->user()->restrict($r->query('assemblyNo')) : null;
        if ($type === 'candidates' && !$r->query('electionId')) $electionId = Election::where('current', true)->value('id');
        else $electionId = $r->query('electionId');
        if ($type === 'candidates' && $electionId) $q->where('election_id', $electionId);
        if ($filterValue) $q->where($d['filter'], $filterValue);
        // sub castes: filter by caste
        $casteId = $type === 'subcastes' && is_numeric($r->query('casteId')) ? (int) $r->query('casteId') : null;
        if ($casteId) $q->where('caste_id', $casteId);
        if ($type === 'subcastes') $q->with('caste');
        // booths: Ward No filter (the ward of a booth comes from its voters)
        $wards = []; $wardNo = is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null;
        if ($type === 'booths') {
            $vs = app(\App\Services\Voters\VoterService::class);
            $wards = $vs->wards($filterValue);
            if ($wardNo !== null) {
                $map = $vs->boothsByWard($filterValue);
                $q->whereIn('booth_no', ($map[$wardNo] ?? []) ?: [0]);
            }
        }
        foreach ($d['order'] as $o) { [$c, $dir] = explode(' ', $o . ' asc'); $q->orderBy($c, $dir); }
        $items = $q->get();
        // status masters: how many voters have each value, and values on voters that are not in the master yet
        $usage = []; $missing = [];
        if (isset(self::STATUS[$type])) {
            $col = self::STATUS[$type];
            $usage = Voter::whereNotNull($col)->where($col, '<>', '')->when($this->user()->workAssembly(), fn ($q, $a) => $q->where('assembly_no', $a))
                ->groupBy($col)->selectRaw("$col as v, count(*) as n")->pluck('n', 'v')->all();
            $known = $items->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
            foreach ($usage as $v => $n) if (!in_array(mb_strtolower($v), $known, true)) $missing[$v] = $n;
            arsort($missing);
        }
        return view('masters.list', ['d' => $d, 'items' => $items, 'usage' => $usage, 'missing' => array_slice($missing, 0, 50, true), 'filterValue' => $filterValue, 'electionId' => $electionId, 'wards' => $wards, 'wardNo' => $wardNo,
            'casteList' => $type === 'subcastes' ? Caste::orderBy('name')->pluck('name', 'id')->all() : [], 'casteId' => $casteId,
            'assemblies' => Assembly::orderBy('assembly_no')->get(), 'elections' => Election::orderByDesc('election_year')->get()]);
    }

    public function form(Request $r, string $type, ?int $id = null)
    {
        $d = $this->def($type);
        $item = $id ? $d['model']::findOrFail($id) : new $d['model']();
        if (!$id) {
            if ($r->query('assemblyNo')) $item->assembly_no = (int) $r->query('assemblyNo');
            if ($type === 'candidates') $item->election_id = $r->query('electionId') ?: Election::where('current', true)->value('id');
            if ($type === 'subcastes' && $r->query('casteId')) $item->caste_id = (int) $r->query('casteId');
            if (in_array($type, ['castes', 'subcastes'], true) || isset(self::STATUS[$type])) $item->active = true;
            if (isset(self::STATUS[$type]) && $r->query('name')) $item->name = mb_substr((string) $r->query('name'), 0, 100);
        }
        return view('masters.form', ['d' => $d, 'item' => $item]);
    }

    public function save(Request $r, string $type)
    {
        $d = $this->def($type);
        $id = $r->input('id');
        $item = $id ? $d['model']::findOrFail($id) : new $d['model']();
        $data = Validation::clean($r->only(array_keys($d['fields'])), []);
        foreach ($d['fields'] as $name => $f) if ($f[1] === 'checkbox') $data[$name] = $r->boolean($name);
        if ($type === 'parties' && isset($data['short_name'])) $data['short_name'] = strtoupper($data['short_name']);
        $rules = array_map(fn ($f) => $f[2], $d['fields']);
        $v = validator($data, $rules, Validation::messages());
        $v->after(function ($val) use ($d, $data, $item) {
            foreach ($d['unique'] as $cols) {
                $q = $d['model']::query();
                foreach ($cols as $c) $q->where($c, $data[$c] ?? null);
                if ($item->exists) $q->where('id', '<>', $item->id);
                if ($q->exists()) $val->errors()->add(end($cols), t('validation.duplicate'));
            }
        });
        if ($v->fails()) { $item->fill($data); return response()->view('masters.form', ['d' => $d, 'item' => $item, 'errors' => $v->errors()]); }
        $isNew = !$item->exists;
        $oldName = $item->getOriginal('name'); $oldCaste = $item instanceof SubCaste ? $item->caste?->name : null;
        $item->fill($data)->save();
        // renaming a caste / sub caste also renames it on the voters that have it
        if (!$isNew && $type === 'castes' && $oldName !== $item->name) Voter::withoutGlobalScope('area')->where('caste', $oldName)->update(['caste' => $item->name]);
        if (!$isNew && $type === 'subcastes' && $oldName !== $item->name) {
            Voter::withoutGlobalScope('area')->where('sub_caste', $oldName)->when($oldCaste, fn ($q) => $q->where('caste', $oldCaste))->update(['sub_caste' => $item->name]);
        }
        if (!$isNew && isset(self::STATUS[$type]) && $oldName !== $item->name) Voter::withoutGlobalScope('area')->where(self::STATUS[$type], $oldName)->update([self::STATUS[$type] => $item->name]);
        if ($type === 'elections' && $item->current) Election::where('id', '<>', $item->id)->update(['current' => false]);
        $this->audit()->log($isNew ? 'CREATE' : 'UPDATE', self::ENTITY[$type], $item->id, $item->name ?? '');
        $back = '/masters/' . $type . ($d['filter'] ? '?assemblyNo=' . $item->{$d['filter']} : '') . ($type === 'subcastes' ? '?casteId=' . $item->caste_id : '');
        return redirect($back)->with('success', 'flash.saved');
    }

    public function delete(string $type, int $id)
    {
        $d = $this->def($type);
        $item = $d['model']::findOrFail($id);
        $inUse = match ($type) {
            'assemblies' => Booth::where('assembly_no', $item->assembly_no)->exists(),
            'parties' => Candidate::where('party_id', $id)->exists(),
            'elections' => Candidate::where('election_id', $id)->exists(),
            'candidates' => BoothResult::where('candidate_id', $id)->exists(),
            'castes' => SubCaste::where('caste_id', $id)->exists() || Voter::withoutGlobalScope('area')->where('caste', $item->name)->exists(),
            'subcastes' => Voter::withoutGlobalScope('area')->where('sub_caste', $item->name)->where('caste', $item->caste?->name)->exists(),
            'socialstatuses', 'politicalstatuses', 'orgstatuses' => Voter::withoutGlobalScope('area')->where(self::STATUS[$type], $item->name)->exists(),
            default => false,
        };
        if ($inUse) return redirect('/masters/' . $type)->with('error', 'flash.inUse');
        $item->delete();
        $this->audit()->log('DELETE', self::ENTITY[$type], $id, $item->name ?? '');
        return redirect('/masters/' . $type)->with('success', 'flash.deleted');
    }
}
