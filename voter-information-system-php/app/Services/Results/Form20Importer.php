<?php

namespace App\Services\Results;

use App\Models\BoothResult;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Party;
use App\Services\Auth\AuditService;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Booth-wise result (Form 20) from Excel or CSV: one row per booth, one column per candidate.
 * The booth column is found by its heading (Booth No / Part No / Polling Station No / ਬੂਥ / बूथ).
 * Candidate columns are matched to the candidates already entered (by name or party short name,
 * e.g. "Ramesh Kumar (INC)" or just "INC"); unknown ones are added. Total / rejected / tendered /
 * electors / serial / name columns are skipped.
 */
class Form20Importer
{
    public function __construct(private AuditService $audit) {}

    private static function key($s): string { return (string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', Text::lower(trim((string) $s))); }

    private static function isBooth(string $k): bool
    {
        static $tr = null;
        $tr ??= array_map(fn ($c) => self::key(\App\Support\Lang::get('col.boothNo', [], $c)), array_keys(\App\Support\Lang::languages()));
        return in_array($k, $tr, true) || in_array($k, ['boothno', 'booth', 'boothnumber', 'partno', 'part', 'partnumber', 'psno', 'ps', 'pollingstationno', 'pollingstation',
            'pollingstationnumber', 'slnoofpollingstation', 'noofpollingstation', 'बूथसं', 'बूथनं', 'बूथ', 'भागसं', 'ਬੂਥਨੰ', 'ਬੂਥ', 'ਭਾਗਨੰ'], true);
    }

    private static function skip(string $k): bool
    {
        if ($k === '') return true;
        foreach (['total', 'reject', 'tender', 'elector', 'name', 'village', 'locality', 'remark', 'serial', 'कुल', 'ਕੁੱਲ', 'ਕੁਲ', 'नाम', 'ਨਾਮ'] as $w) if (str_contains($k, $w)) return true;
        return in_array($k, ['slno', 'sno', 'sl', 'sr', 'srno', 'no', 'valid', 'validvotes', 'postal', 'postalvotes', 'क्रसं', 'ਲੜੀਨੰ'], true);
    }

    /** Header cell to [name, party short or null]: "Ramesh Kumar (INC)" -> ["Ramesh Kumar", "INC"]. */
    private static function split(string $h): array
    {
        $h = trim(preg_replace('/\s+/u', ' ', $h));
        if (preg_match('/^(.*?)[\s\-]*[\(\[]\s*([^\)\]]+?)\s*[\)\]]\s*$/u', $h, $m) && trim($m[1]) !== '') return [trim($m[1]), strtoupper(trim($m[2]))];
        if (preg_match('/^(.*?)\s+-\s+(\S+)$/u', $h, $m)) return [trim($m[1]), strtoupper(trim($m[2]))];
        return [$h, null];
    }

    private function rows(string $path, string $ext): \Generator
    {
        $reader = strtolower($ext) === 'csv' ? new CsvReader() : new XlsxReader();
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) yield array_map(fn ($c) => $c instanceof \DateTimeInterface ? '' : $c, $row->toArray());
                break;   // first sheet only
            }
        } finally {
            $reader->close();
        }
    }

    /** @return array{booths:int, votes:int, added:string[], matched:string[], skipped:string[], errors:string[]} */
    public function import(string $path, string $ext, Election $e, int $assemblyNo, string $user): array
    {
        $out = ['booths' => 0, 'votes' => 0, 'added' => [], 'matched' => [], 'skipped' => [], 'errors' => []];
        $boothCol = null; $cols = []; $data = []; $line = 0;
        foreach ($this->rows($path, $ext) as $row) {
            $line++;
            if ($boothCol === null) {
                if ($line > 10) break;
                foreach ($row as $i => $cell) if (self::isBooth(self::key($cell))) { $boothCol = $i; break; }
                if ($boothCol === null) continue;
                foreach ($row as $i => $cell) {
                    if ($i === $boothCol) continue;
                    $k = self::key($cell);
                    if (self::skip($k)) { if (trim((string) $cell) !== '') $out['skipped'][] = trim((string) $cell); continue; }
                    $cols[$i] = trim((string) $cell);
                }
                continue;
            }
            $bRaw = Text::asciiDigits(trim((string) ($row[$boothCol] ?? '')));
            if (!preg_match('/^\d+/', $bRaw, $m)) continue;   // blank, total or note rows
            $b = (int) $m[0];
            if ($b <= 0) continue;
            foreach ($cols as $i => $h) {
                $v = trim(Text::asciiDigits((string) ($row[$i] ?? '')));
                $v = str_replace([',', ' '], '', $v);
                if ($v === '' || !is_numeric($v)) continue;
                $data[$b][$i] = ($data[$b][$i] ?? 0) + max(0, (int) $v);
            }
        }
        if ($boothCol === null) { $out['errors'][] = 'form20.noBoothColumn'; return $out; }
        if (!$cols) { $out['errors'][] = 'form20.noCandidates'; return $out; }
        if (!$data) { $out['errors'][] = 'form20.noRows'; return $out; }

        DB::transaction(function () use ($e, $assemblyNo, $cols, $data, $user, &$out) {
            $cands = Candidate::with('party')->where('election_id', $e->id)->where('assembly_no', $assemblyNo)->get();
            $parties = Party::all()->keyBy(fn ($p) => strtoupper($p->short_name));
            $ballot = (int) $cands->max('ballot_no');
            $colCand = [];
            foreach ($cols as $i => $h) {
                [$name, $short] = self::split($h);
                $kn = self::key($name);
                $c = $cands->first(fn ($c) => self::key($c->name) === $kn || ($c->name_local && self::key($c->name_local) === $kn))
                    ?? ($short === null ? $cands->first(fn ($c) => $c->party && strtoupper($c->party->short_name) === strtoupper($name)) : null)
                    ?? ($short !== null ? $cands->first(fn ($c) => $c->party && strtoupper($c->party->short_name) === $short && self::key($c->name) === $kn) : null);
                if ($c) { $out['matched'][] = $h; $colCand[$i] = $c->id; continue; }
                // a bare party short name ("INC") as the heading: the party's candidate, named after the party
                $party = $parties[$short ?? strtoupper($name)] ?? null;
                $c = Candidate::create(['election_id' => $e->id, 'assembly_no' => $assemblyNo, 'name' => mb_substr($name, 0, 120),
                    'party_id' => $party?->id, 'ballot_no' => ++$ballot]);
                $cands->push($c->setRelation('party', $party));
                $out['added'][] = $h;
                $colCand[$i] = $c->id;
            }
            foreach ($data as $b => $votes) {
                foreach ($votes as $i => $v) {
                    BoothResult::updateOrCreate(['candidate_id' => $colCand[$i], 'booth_no' => $b],
                        ['election_id' => $e->id, 'assembly_no' => $assemblyNo, 'votes' => $v, 'entered_by' => $user, 'updated_at' => now()]);
                    $out['votes'] += $v;
                }
                $out['booths']++;
            }
        });
        $this->audit->log('RESULT_IMPORT', 'RESULT', $e->id . '/' . $assemblyNo, "Form 20: {$out['booths']} booths, {$out['votes']} votes, "
            . count($out['added']) . ' candidates added');
        return $out;
    }
}
