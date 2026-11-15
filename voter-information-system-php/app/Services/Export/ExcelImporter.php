<?php

namespace App\Services\Export;

use App\Models\Voter;
use App\Services\Voters\VoterService;
use App\Support\Lang;
use App\Support\Text;
use App\Support\Validation;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public function __construct(private VoterService $voters) {}

    private static function norm($s): string { return Text::lower((string) $s); }

    public function import(string $path, ?int $onlyAssembly): array
    {
        $headerMap = []; $genders = []; $relations = [];
        foreach (array_keys(Lang::languages()) as $code) {
            foreach (self::FIELD_BY_KEY as $key => $field) $headerMap[self::norm(Lang::get($key, [], $code))] = $field;
            foreach (Voter::GENDERS as $g) $genders[self::norm(Lang::get("gender.$g", [], $code))] = $g;
            foreach (Voter::RELATIONS as $r) $relations[self::norm(Lang::get("relation.$r", [], $code))] = $r;
        }
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $idx = null; $headerRow = null;
        foreach (array_slice($sheet, 0, 6) as $r => $row) {
            $m = [];
            foreach ($row as $i => $cell) if (isset($headerMap[self::norm($cell)])) $m[$headerMap[self::norm($cell)]] ??= $i;
            if (isset($m['epic_no'], $m['name'])) { $idx = $m; $headerRow = $r; break; }
        }
        if ($idx === null) return ['inserted' => 0, 'updated' => 0, 'errors' => [t('import.error.header')]];

        $inserted = 0; $updated = 0; $errors = [];
        foreach (array_slice($sheet, $headerRow + 1, null, true) as $r => $row) {
            $get = fn ($f) => isset($idx[$f]) ? trim((string) ($row[$idx[$f]] ?? '')) : '';
            if ($get('epic_no') === '' && $get('name') === '') continue;
            $label = t('import.row', $r + 1);
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
            $data = Validation::clean($data);
            $v = Validator::make($data, VoterService::rules(), Validation::messages());
            if ($v->fails()) {
                $field = array_key_first($v->errors()->messages());
                $errors[] = $label . ': ' . t('field.' . \Illuminate\Support\Str::camel($field)) . ' - ' . $v->errors()->first($field);
                continue;
            }
            $voter = Voter::where('epic_no', strtoupper((string) Text::asciiDigits($data['epic_no'])))->first() ?? new Voter();
            if ($onlyAssembly !== null && ($data['assembly_no'] !== $onlyAssembly || ($voter->exists && $voter->assembly_no !== $onlyAssembly))) {
                $errors[] = $label . ': ' . t('import.error.assembly', $onlyAssembly);
                continue;
            }
            $isNew = !$voter->exists;
            $voter->fill(array_intersect_key($data, array_flip(array_values(self::FIELD_BY_KEY))));
            try { $voter->save(); $isNew ? $inserted++ : $updated++; }
            catch (\Throwable $e) { $errors[] = $label . ': ' . $e->getMessage(); }
            if (count($errors) > 200) { $errors[] = t('import.error.tooMany'); break; }
        }
        return compact('inserted', 'updated', 'errors');
    }
}
