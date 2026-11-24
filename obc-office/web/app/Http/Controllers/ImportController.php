<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CustomField;
use App\Models\Designation;
use App\Models\District;
use App\Models\Lookup;
use App\Models\Member;
use App\Models\State;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** CSV import (states' Excel sheets saved as CSV) and CSV export of members. */
class ImportController extends Controller implements HasMiddleware
{
    private const COLUMNS = ['name', 'father_name', 'gender', 'dob', 'mobile', 'whatsapp', 'email', 'type', 'level',
        'state', 'district', 'mandal', 'designation', 'appointed_on', 'profession', 'education', 'address', 'consent'];

    public static function middleware(): array
    {
        return [new Middleware('module:import,manage')];
    }

    public function form()
    {
        return view('members.import', ['columns' => $this->columns()]);
    }

    private function columns(): array
    {
        return array_merge(self::COLUMNS, CustomField::for('member')->pluck('key')->all());
    }

    public function template()
    {
        $cols = $this->columns();
        $sample = ['Ramesh Kumar', 'Suresh Kumar', 'male', '1980-05-14', '9876543210', '', '', 'bearer', 'district',
            'Uttar Pradesh', 'Lucknow', '', 'President', '2026-01-15', 'Advocate', 'Graduate', 'Lucknow', 'yes'];

        return response()->streamDownload(function () use ($cols, $sample) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Hindi names correctly
            fputcsv($out, $cols);
            fputcsv($out, array_pad($sample, count($cols), ''));
            fclose($out);
        }, 'members-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240', 'mode' => 'required|in:skip,update']);
        $user = $request->user();
        $svc = new MemberService($user);

        $fh = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($fh);
        if (! $header) {
            return back()->withErrors(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        if (! in_array('name', $header, true)) {
            return back()->withErrors(['file' => 'A "name" column is required. Download the template for the expected columns.']);
        }

        $states = State::all()->keyBy(fn ($s) => Str::lower($s->name));
        $districts = District::all()->groupBy('state_id');
        $designations = Designation::all();
        $levels = Lookup::options('level', true);
        $types = Lookup::options('member_type', true);
        $customKeys = CustomField::for('member')->pluck('key')->all();

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $line = 1;

        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $r = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $r = array_map(fn ($v) => trim((string) $v), $r);

            try {
                $level = Str::lower($r['level'] ?? '') ?: 'district';
                if (! isset($levels[$level])) {
                    throw new \RuntimeException("unknown level '$level'");
                }
                $state = isset($r['state']) && $r['state'] !== '' ? $states->get(Str::lower($r['state'])) : null;
                if (($r['state'] ?? '') !== '' && ! $state) {
                    throw new \RuntimeException("unknown state '{$r['state']}'");
                }
                $district = null;
                if (($r['district'] ?? '') !== '') {
                    $district = $state ? ($districts[$state->id] ?? collect())->first(fn ($d) => Str::lower($d->name) === Str::lower($r['district'])) : null;
                    if (! $district && $state && $user->isNational()) {
                        $district = District::create(['state_id' => $state->id, 'name' => $r['district']]);
                        $districts[$state->id] = ($districts[$state->id] ?? collect())->push($district);
                    }
                    if (! $district) {
                        throw new \RuntimeException("unknown district '{$r['district']}'");
                    }
                }
                $designation = ($r['designation'] ?? '') !== ''
                    ? $designations->first(fn ($d) => $d->level === $level && Str::lower($d->title) === Str::lower($r['designation']))
                    : null;
                if (($r['designation'] ?? '') !== '' && ! $designation) {
                    throw new \RuntimeException("designation '{$r['designation']}' is not configured for level '$level'");
                }

                $data = [
                    'name' => $r['name'] ?? '', 'father_name' => $r['father_name'] ?? null,
                    'gender' => Str::lower($r['gender'] ?? '') ?: null, 'dob' => $this->date($r['dob'] ?? null),
                    'mobile' => preg_replace('/\D/', '', $r['mobile'] ?? '') ?: null,
                    'whatsapp' => preg_replace('/\D/', '', $r['whatsapp'] ?? '') ?: null,
                    'email' => $r['email'] ?? null,
                    'type' => isset($types[Str::lower($r['type'] ?? '')]) ? Str::lower($r['type']) : 'bearer',
                    'level' => $level, 'state_id' => $state?->id, 'district_id' => $district?->id,
                    'mandal' => $r['mandal'] ?? null, 'designation_id' => $designation?->id,
                    'appointed_on' => $this->date($r['appointed_on'] ?? null),
                    'profession' => $r['profession'] ?? null, 'education' => $r['education'] ?? null,
                    'address' => $r['address'] ?? null,
                    'consent' => in_array(Str::lower($r['consent'] ?? ''), ['1', 'yes', 'y', 'true', 'haan', 'हाँ'], true) ? '1' : null,
                    'extra' => collect($customKeys)->mapWithKeys(fn ($k) => [$k => ($r[$k] ?? '') !== '' ? $r[$k] : null])->all(),
                ];

                $existing = $data['mobile'] ? Member::where('mobile', $data['mobile'])->where('status', '!=', 'removed')->first() : null;
                if ($existing && $request->input('mode') === 'skip') {
                    $result['skipped']++;

                    continue;
                }
                if ($existing) {
                    $svc->authorize($existing);
                }

                // Reuse the same validation + jurisdiction rules as the form.
                $sub = Request::create('/', 'POST', $data + ['allow_duplicate' => '1']);
                $sub->setUserResolver(fn () => $user);
                $svc->save($sub, $existing);
                $existing ? $result['updated']++ : $result['created']++;
            } catch (ValidationException $e) {
                $result['errors'][] = "Row $line ({$r['name']}): ".collect($e->errors())->flatten()->implode(' ');
            } catch (\Throwable $e) {
                $result['errors'][] = "Row $line (".($r['name'] ?? '?').'): '.$e->getMessage();
            }
        }
        fclose($fh);

        AuditLog::record('import', null, "created {$result['created']}, updated {$result['updated']}, errors ".count($result['errors']));

        return back()->with('import_result', $result);
    }

    public function export(Request $request)
    {
        $query = (new MemberService($request->user()))->query($request)->orderBy('state_id')->orderBy('district_id')->orderBy('name');
        $customKeys = CustomField::for('member')->pluck('key')->all();
        AuditLog::record('export', null, http_build_query($request->query()));

        return response()->streamDownload(function () use ($query, $customKeys) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['id', 'status'], self::COLUMNS, $customKeys));
            $query->chunk(500, function ($rows) use ($out, $customKeys) {
                foreach ($rows as $m) {
                    fputcsv($out, array_merge([
                        $m->id, $m->status, $m->name, $m->father_name, $m->gender, $m->dob?->toDateString(), $m->mobile, $m->whatsapp,
                        $m->email, $m->type, $m->level, $m->state?->name, $m->district?->name, $m->mandal, $m->designation?->title,
                        $m->appointed_on?->toDateString(), $m->profession, $m->education, $m->address, $m->consent ? 'yes' : 'no',
                    ], collect($customKeys)->map(fn ($k) => $m->extra[$k] ?? '')->all()));
                }
            });
            fclose($out);
        }, 'members-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function date(?string $v): ?string
    {
        if (! $v) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $fmt) {
            $d = \DateTime::createFromFormat('!'.$fmt, $v);
            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d');
            }
        }

        return $v; // let validation report it
    }
}
