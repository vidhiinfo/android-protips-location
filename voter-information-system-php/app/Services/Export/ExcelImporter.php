<?php

namespace App\Services\Export;

use App\Models\Voter;
use App\Services\Voters\VoterService;
use App\Support\Lang;
use App\Support\Text;
use App\Support\Validation;
use App\Support\FastValidator;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Bulk upload from .xlsx. Headers and gender / relation values are recognised in every supported language,
 * so a file exported in Hindi or Urdu can be uploaded again. Matched on EPIC No: existing voters updated, others added.
 */
class ExcelImporter
{
    private const FIELD_BY_KEY = [
        'col.assemblyNo' => 'assembly_no', 'col.assemblyName' => 'assembly_name', 'col.boothNo' => 'booth_no', 'col.serialNo' => 'serial_no',
        'col.epicNo' => 'epic_no', 'col.name' => 'name', 'col.nameLocal' => 'name_local', 'col.relation' => 'relation_type',
        'col.relativeName' => 'relative_name', 'col.relativeNameLocal' => 'relative_name_local', 'col.gender' => 'gender', 'col.age' => 'age',
        'col.houseNo' => 'house_no', 'col.address' => 'address', 'col.addressLocal' => 'address_local', 'col.villageWard' => 'village_ward',
        'col.district' => 'district', 'col.pincode' => 'pincode', 'col.mobile' => 'mobile', 'col.altMobile' => 'alt_mobile', 'col.email' => 'email',
        'col.boothName' => 'booth_name', 'col.category' => 'category', 'col.remarks' => 'remarks',
    ];

    /**
     * Other common column titles (compared without spaces, dots and brackets), e.g. a ward-wise list:
     * Ward | Booth | V.SNo. | VoterID | Name | Gender | Relative's Name | Age | Houseno | Address | Mobile(Original) | Mobile1 |
     * Membershipno | DOB | Marriage Anniv. | Deleted | Shift1 | Shift2 | Place of Origin | Current Place | Linked With |
     * Social Status | Political Status | Organisational Status | Beneficiary1-3 | Caste | Poling Station
     */
    private const ALIASES = [
        'assembly' => 'assembly_no', 'assemblyno' => 'assembly_no', 'acno' => 'assembly_no', 'ac' => 'assembly_no', 'vidhansabha' => 'assembly_no',
        'ward' => 'village_ward', 'wardno' => 'village_ward', 'village' => 'village_ward',
        'booth' => 'booth_no', 'boothno' => 'booth_no', 'partno' => 'booth_no', 'part' => 'booth_no',
        'vsno' => 'serial_no', 'sno' => 'serial_no', 'slno' => 'serial_no', 'serialno' => 'serial_no', 'voterserialno' => 'serial_no',
        'voterid' => 'epic_no', 'epic' => 'epic_no', 'epicno' => 'epic_no', 'votercardno' => 'epic_no', 'idcardno' => 'epic_no',
        'name' => 'name', 'votername' => 'name', 'electorname' => 'name',
        'relativesname' => 'relative_name', 'relativename' => 'relative_name', 'fathername' => 'relative_name', 'fathersname' => 'relative_name',
        'fatherhusbandname' => 'relative_name', 'guardianname' => 'relative_name',
        'gender' => 'gender', 'sex' => 'gender', 'age' => 'age', 'houseno' => 'house_no', 'house' => 'house_no', 'address' => 'address',
        'mobileoriginal' => 'mobile', 'mobile' => 'mobile', 'mobileno' => 'mobile', 'phone' => 'mobile',
        'mobile1' => 'alt_mobile', 'mobile2' => 'alt_mobile', 'altmobile' => 'alt_mobile', 'alternatemobile' => 'alt_mobile',
        'membershipno' => 'membership_no', 'membership' => 'membership_no',
        'dob' => 'dob', 'dateofbirth' => 'dob', 'birthdate' => 'dob',
        'marriageanniv' => 'anniversary', 'marriageanniversary' => 'anniversary', 'anniversary' => 'anniversary', 'annivdate' => 'anniversary',
        'deleted' => 'deleted', 'shift1' => 'shift1', 'shift2' => 'shift2',
        'placeoforigin' => 'place_of_origin', 'nativeplace' => 'place_of_origin', 'currentplace' => 'current_place',
        'linkedwith' => 'linked_with', 'socialstatus' => 'social_status', 'politicalstatus' => 'political_status',
        'organisationalstatus' => 'organisational_status', 'organizationalstatus' => 'organisational_status',
        'beneficiary1' => 'beneficiary1', 'beneficiary2' => 'beneficiary2', 'beneficiary3' => 'beneficiary3', 'beneficiary' => 'beneficiary1',
        'caste' => 'caste', 'polingstation' => 'booth_name', 'pollingstation' => 'booth_name', 'boothname' => 'booth_name', 'pollingstationname' => 'booth_name',
        'category' => 'category', 'remarks' => 'remarks', 'email' => 'email', 'pincode' => 'pincode', 'district' => 'district',
    ];

    public function __construct(private VoterService $voters) {}

    private static function norm($s): string { return Text::lower((string) $s); }

    /** Header comparison: lower case, letters and digits only ("Mobile(Original)" = "mobile original"). */
    private static function headerKey($s): string { return (string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', Text::lower((string) $s)); }

    /** dd-mm-yyyy, dd/mm/yyyy, dd.mm.yyyy, yyyy-mm-dd, 15-Aug-1982, Excel date numbers -> yyyy-mm-dd; unknown text is returned unchanged (then rejected). */
    public static function parseDate($v): ?string
    {
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
        $s = trim((string) Text::asciiDigits((string) $v));
        if ($s === '') return null;
        if (preg_match('/^\d{4,5}(\.0+)?$/', $s) && (int) $s > 366) {   // Excel serial day number
            return (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) $s . ' days')->format('Y-m-d');
        }
        foreach (['!d-m-Y', '!d/m/Y', '!d.m.Y', '!Y-m-d', '!Y/m/d', '!d-M-Y', '!d M Y', '!d-m-y', '!d/m/y', '!j-n-Y', '!j/n/Y'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat($fmt, $s);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($d && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $d->format('Y-m-d');
        }
        return $s;
    }

    public function import(string $path, ?int $onlyAssembly, ?int $defaultAssembly = null): array
    {
        $headerMap = self::ALIASES; $genders = []; $relations = []; $yes = ['yes' => 1, 'y' => 1, '1' => 1, 'true' => 1, 'deleted' => 1, 'd' => 1, 'del' => 1];
        $keys = self::FIELD_BY_KEY + array_combine(array_map(fn ($d) => 'col.' . $d[0], Voter::PROFILE), array_keys(Voter::PROFILE));
        foreach (array_keys(Lang::languages()) as $code) {
            foreach ($keys as $key => $field) $headerMap[self::headerKey(Lang::get($key, [], $code))] = $field;
            $yes[self::norm(Lang::get('common.yes', [], $code))] = 1;
            $yes[self::norm(Lang::get('col.deleted', [], $code))] = 1;
            foreach (Voter::GENDERS as $g) $genders[self::norm(Lang::get("gender.$g", [], $code))] = $g;
            foreach (Voter::RELATIONS as $r) $relations[self::norm(Lang::get("relation.$r", [], $code))] = $r;
        }
        @set_time_limit(0);
        $reader = new Reader();
        $reader->open($path);
        $validator = null;
        $idx = null; $inserted = 0; $updated = 0; $errors = []; $buf = [];
        $fields = array_flip(array_values($keys));
        // saves 500 rows per transaction, looking up their EPIC numbers with one query
        $save = function () use (&$buf, &$inserted, &$updated, &$errors, $onlyAssembly, $fields) {
            $epic = fn ($d) => strtoupper((string) Text::asciiDigits($d['epic_no']));
            $existing = Voter::whereIn('epic_no', array_map(fn ($b) => $epic($b[1]), $buf))->get()->keyBy('epic_no')->all();
            foreach ($buf as [$label, $data]) {
                $voter = $existing[$epic($data)] ?? new Voter();
                if ($onlyAssembly !== null && ($data['assembly_no'] !== $onlyAssembly || ($voter->exists && $voter->assembly_no !== $onlyAssembly))) {
                    $errors[] = t('import.row', $label) . ': ' . t('import.error.assembly', $onlyAssembly);
                    continue;
                }
                $isNew = !$voter->exists;
                $voter->fill(array_intersect_key($data, $fields));
                try { $voter->save(); $isNew ? $inserted++ : $updated++; $existing[$voter->epic_no] = $voter; }
                catch (\Throwable $e) { $errors[] = t('import.row', $label) . ': ' . $e->getMessage(); }
            }
            $buf = [];
            DB::commit();
            DB::beginTransaction();
        };
        DB::beginTransaction();
        try {
        foreach ($reader->getSheetIterator() as $sheet) {
        // rows are streamed one by one: a file with lakhs of voters does not have to fit in memory
        foreach ($sheet->getRowIterator() as $rowNo => $rowObj) {
            $row = array_map(fn ($c) => $c instanceof \DateTimeInterface ? $c->format('Y-m-d') : $c, $rowObj->toArray());
            if ($idx === null) {
                if ($rowNo > 6) break;
                $m = [];
                foreach ($row as $i => $cell) if (isset($headerMap[self::headerKey($cell)])) $m[$headerMap[self::headerKey($cell)]] ??= $i;
                if (isset($m['epic_no'], $m['name'])) $idx = $m;
                continue;
            }
            $r = $rowNo - 1;
            $get = fn ($f) => isset($idx[$f]) ? trim((string) ($row[$idx[$f]] ?? '')) : '';
            if ($get('epic_no') === '' && $get('name') === '') continue;
            $label = $r + 1;
            $data = [];
            foreach ($idx as $field => $i) $data[$field] = $get($field);
            $data['gender'] = $genders[self::norm($data['gender'] ?? '')] ?? match (strtoupper(substr($data['gender'] ?? '', 0, 1))) { 'M' => 'MALE', 'F' => 'FEMALE', 'O', 'T' => 'OTHER', default => null };
            $data['relation_type'] = $relations[self::norm($data['relation_type'] ?? '')] ?? 'FATHER';
            foreach (['age', 'assembly_no', 'booth_no', 'serial_no'] as $n) {
                $v = Text::asciiDigits($data[$n] ?? '');
                $data[$n] = is_numeric($v) ? (int) $v : null;
            }
            foreach (['mobile', 'alt_mobile'] as $m) {
                $d = preg_replace('/[^0-9]/', '', (string) Text::asciiDigits($data[$m] ?? ''));
                $data[$m] = strlen($d) === 12 && str_starts_with($d, '91') ? substr($d, 2) : ($d ?: null);
            }
            // ward / booth lists usually have no assembly column: use the one chosen on the import page
            $data['assembly_no'] ??= $onlyAssembly ?? $defaultAssembly;
            foreach (['dob', 'anniversary'] as $dField) if (isset($idx[$dField])) $data[$dField] = self::parseDate($row[$idx[$dField]] ?? '');
            if (isset($idx['deleted'])) $data['deleted'] = isset($yes[self::norm($data['deleted'] ?? '')]) ? 1 : 0;
            if ($data['age'] === null && !empty($data['dob']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['dob'])) {
                $data['age'] = (new \DateTimeImmutable($data['dob']))->diff(new \DateTimeImmutable('today'))->y ?: null;
            }
            $data = Validation::clean($data);
            $validator ??= new FastValidator(VoterService::rules(), Validation::messages());
            if ($err = $validator->firstError($data)) {
                $errors[] = t('import.row', $label) . ': ' . t('field.' . \Illuminate\Support\Str::camel($err[0])) . ' - ' . $err[1];
                continue;
            }
            $buf[] = [$label, $data];
            if (count($buf) >= 500) $save();
            if (count($errors) > 200) { $errors[] = t('import.error.tooMany'); break; }
        }
        break;   // first sheet only
        }
        if ($buf) $save();
        DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $reader->close();
        }
        if ($idx === null) return ['inserted' => 0, 'updated' => 0, 'errors' => [t('import.error.header')]];
        return compact('inserted', 'updated', 'errors');
    }
}
