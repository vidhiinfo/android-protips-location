<?php

namespace App\Services\Voters;

use App\Support\Text;
use Illuminate\Http\Request;

/**
 * Search / filter / sort / paging parameters from the query string. The same parameters drive the list,
 * Excel / PDF export, print, and the recipients of SMS / WhatsApp campaigns.
 */
class VoterFilter
{
    public const SORTABLE = ['epicNo' => 'epic_no', 'name' => 'name', 'nameLocal' => 'name_local', 'relativeName' => 'relative_name',
        'gender' => 'gender', 'age' => 'age', 'assemblyNo' => 'assembly_no', 'boothNo' => 'booth_no', 'serialNo' => 'serial_no',
        'villageWard' => 'village_ward', 'wardNo' => 'ward_no', 'district' => 'district', 'mobile' => 'mobile', 'updatedAt' => 'updated_at'];
    public const FIELDS = ['q', 'name', 'relativeName', 'epicNo', 'mobile', 'assemblyNo', 'wardNo', 'boothNo', 'gender', 'ageMin', 'ageMax',
        'district', 'villageWard', 'houseNo', 'hasMobile', 'caste', 'subCaste', 'politicalStatus', 'beneficiary', 'membershipNo', 'deleted', 'sentiment'];

    public array $v = [];
    public string $sort = 'assemblyNo';
    public string $dir = 'asc';
    public int $page = 0;
    public int $size = 25;

    public static function fromRequest(Request $r): self
    {
        $f = new self();
        foreach (self::FIELDS as $k) {
            $val = $r->query($k, $r->input($k));
            if ($val !== null && $val !== '') $f->v[$k] = is_string($val) ? trim($val) : $val;
        }
        foreach (['assemblyNo', 'wardNo', 'boothNo', 'ageMin', 'ageMax'] as $k) {
            if (isset($f->v[$k])) { if (is_numeric($f->v[$k])) $f->v[$k] = (int) $f->v[$k]; else unset($f->v[$k]); }
        }
        if (isset($f->v['gender']) && !in_array($f->v['gender'], ['MALE', 'FEMALE', 'OTHER'], true)) unset($f->v['gender']);
        if (isset($f->v['deleted']) && !in_array($f->v['deleted'], ['exclude', 'only'], true)) unset($f->v['deleted']);
        if (isset($f->v['sentiment']) && !in_array($f->v['sentiment'], ['S', 'N', 'O', 'X', 'U'], true)) unset($f->v['sentiment']);
        if (isset($f->v['hasMobile'])) $f->v['hasMobile'] = filter_var($f->v['hasMobile'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        $sort = (string) $r->input('sort', 'assemblyNo');
        $f->sort = array_key_exists($sort, self::SORTABLE) ? $sort : 'assemblyNo';
        $f->dir = strtolower((string) $r->input('dir')) === 'desc' ? 'desc' : 'asc';
        $f->page = max(0, (int) $r->input('page', 0));
        $f->size = min(max((int) $r->input('size', 25), 10), 500);
        return $f;
    }

    public function get(string $k) { return $this->v[$k] ?? null; }
    public function set(string $k, $val): void { if ($val === null || $val === '') unset($this->v[$k]); else $this->v[$k] = $val; }

    public function active(): bool { return !empty($this->v); }

    public function filterQuery(): string { return http_build_query($this->v, '', '&', PHP_QUERY_RFC3986); }

    public function queryWithSort(): string
    {
        $f = $this->filterQuery();
        return ($f === '' ? '' : $f . '&') . 'sort=' . $this->sort . '&dir=' . $this->dir;
    }

    public function sortLink(string $col): string
    {
        $newDir = $col === $this->sort && $this->dir === 'asc' ? 'desc' : 'asc';
        $f = $this->filterQuery();
        return '?' . ($f === '' ? '' : $f . '&') . "sort={$col}&dir={$newDir}&size={$this->size}";
    }

    public function pageLink(int $p): string { return '?' . $this->queryWithSort() . "&size={$this->size}&page={$p}"; }

    public function hiddenParams(): array { return $this->v + ['sort' => $this->sort, 'dir' => $this->dir]; }

    /** Orders like a printed roll: chosen column, then assembly, booth, serial. */
    public function applySort($q)
    {
        $q->orderBy(self::SORTABLE[$this->sort], $this->dir);
        if ($this->sort !== 'assemblyNo') $q->orderBy('assembly_no');
        return $q->orderBy('booth_no')->orderBy('serial_no')->orderBy('id');
    }

    /** Parameterised WHERE clauses (SQL-injection safe); text search covers English and local-language columns. */
    public function apply($q)
    {
        $like = fn ($s) => '%' . addcslashes(Text::lower(Text::asciiDigits($s)), '%_\\') . '%';
        if ($s = $this->get('q')) {
            foreach (preg_split('/\s+/u', (string) Text::nfc($s)) as $term) {
                $l = $like($term);
                $q->where(function ($w) use ($l) {
                    foreach (['name', 'name_local', 'relative_name', 'relative_name_local', 'epic_no', 'mobile', 'alt_mobile', 'house_no',
                                 'address', 'address_local', 'village_ward', 'booth_name', 'membership_no', 'caste'] as $c) {
                        $w->orWhereRaw("lower($c) like ?", [$l]);
                    }
                });
            }
        }
        foreach (['name' => ['name', 'name_local'], 'relativeName' => ['relative_name', 'relative_name_local'], 'epicNo' => ['epic_no'],
                     'mobile' => ['mobile', 'alt_mobile'], 'membershipNo' => ['membership_no'],
                     'beneficiary' => ['beneficiary1', 'beneficiary2', 'beneficiary3']] as $k => $cols) {
            if ($s = $this->get($k)) {
                $l = $like($s);
                $q->where(function ($w) use ($cols, $l) { foreach ($cols as $c) $w->orWhereRaw("lower($c) like ?", [$l]); });
            }
        }
        if ($s = $this->get('houseNo')) $q->whereRaw(Text::HOUSE_KEY_SQL . ' = ?', [Text::houseKey($s)]);   // RZ-154 = R Z-154
        foreach (['assemblyNo' => 'assembly_no', 'wardNo' => 'ward_no', 'boothNo' => 'booth_no', 'gender' => 'gender', 'district' => 'district', 'villageWard' => 'village_ward',
                     'caste' => 'caste', 'subCaste' => 'sub_caste', 'politicalStatus' => 'political_status'] as $k => $c) {
            if (($val = $this->get($k)) !== null) $q->where($c, $val);
        }
        if (($a = $this->get('ageMin')) !== null) $q->where('age', '>=', $a);
        if (($a = $this->get('ageMax')) !== null) $q->where('age', '<=', $a);
        if ($s = $this->get('sentiment')) $q->whereRaw(\App\Services\Reports\AnalyticsService::SENTIMENT_SQL . ' = ?', [$s]);   // support / neutral / opposition (Analytics)
        if ($this->get('hasMobile') === 'true') $q->whereNotNull('mobile')->where('mobile', '<>', '');
        if ($this->get('deleted') === 'exclude') $q->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'));
        if ($this->get('deleted') === 'only') $q->where('deleted', true);
        if ($this->get('hasMobile') === 'false') $q->where(fn ($w) => $w->whereNull('mobile')->orWhere('mobile', ''));
        return $q;
    }
}
