<?php

namespace App\Http\Controllers;

use App\Models\Voter;
use App\Services\Privacy\PrivacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Consent per voter, access / erasure requests, the privacy request register and retention (Admin > Privacy). */
class PrivacyController extends Controller
{
    public function __construct(private PrivacyService $privacy) {}

    private function voter(int $id): Voter
    {
        $v = Voter::find($id);
        abort_if(!$v || !$this->user()->allowsVoter($v), 404, t('error.notFound'));
        return $v;
    }

    public function consent(Request $r, int $id)
    {
        $v = $this->voter($id);
        $this->privacy->setConsent($v, $r->input('consent'), (string) $r->input('source', 'other'), $r->input('note'), $this->user()->username());
        return redirect('/voters/' . $v->id)->with('success', 'privacy.consentSaved');
    }

    public function export(int $id)
    {
        $v = $this->voter($id);
        $json = json_encode($this->privacy->export($v), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return response($json, 200, ['Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="voter-data-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $v->epic_no) . '.json"']);
    }

    public function erase(Request $r, int $id)
    {
        $v = $this->voter($id);
        if (strtoupper(trim((string) $r->input('confirm'))) !== strtoupper((string) $v->epic_no)) return redirect('/voters/' . $v->id)->with('error', 'privacy.eraseConfirmWrong');
        $this->privacy->erase($v, $this->user()->username(), mb_substr((string) $r->input('reason', 'request'), 0, 100) ?: 'request');
        if ($rid = (int) $r->input('request_id')) DB::table('privacy_requests')->where('id', $rid)->update(['status' => 'DONE', 'done_by' => $this->user()->username(), 'done_at' => now(), 'outcome' => 'Data erased']);
        return redirect('/voters/' . $v->id)->with('success', 'privacy.erased');
    }

    // ------------------------------------------------------------------ register

    public function index(Request $r)
    {
        $status = in_array($r->query('status'), ['OPEN', 'DONE', 'REJECTED', 'ALL'], true) ? $r->query('status') : 'OPEN';
        $q = DB::table('privacy_requests')->orderByRaw("case when status = 'OPEN' then 0 else 1 end")->orderBy('due_at')->orderByDesc('id')
            ->when($status !== 'ALL', fn ($w) => $w->where('status', $status))
            ->when($this->user()->scoped(), fn ($w) => $w->where('assembly_no', $this->user()->workAssembly()));
        return view('admin.privacy', ['list' => $q->limit(300)->get(), 'status' => $status,
            'counts' => DB::table('privacy_requests')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'overdue' => DB::table('privacy_requests')->where('status', 'OPEN')->where('due_at', '<', now())->count(),
            'assemblies' => app(\App\Services\Voters\VoterService::class)->assemblies()]);
    }

    public function store(Request $r)
    {
        $type = in_array($r->input('type'), PrivacyService::TYPES, true) ? $r->input('type') : 'OTHER';
        $epic = strtoupper(trim((string) $r->input('epic_no')));
        $v = $epic !== '' ? Voter::where('epic_no', $epic)->first() : null;
        $name = trim((string) $r->input('name')) ?: ($v?->name ?? '');
        if ($name === '') return redirect('/admin/privacy')->with('error', 'privacy.needName');
        $id = DB::table('privacy_requests')->insertGetId(['voter_id' => $v?->id, 'assembly_no' => $v?->assembly_no ?? $this->user()->workAssembly(), 'name' => mb_substr($name, 0, 150),
            'epic_no' => $epic ?: null, 'type' => $type, 'channel' => mb_substr((string) $r->input('channel'), 0, 30) ?: null,
            'details' => mb_substr((string) $r->input('details'), 0, 1000) ?: null, 'status' => 'OPEN', 'created_by' => $this->user()->username(),
            'requested_at' => now(), 'due_at' => now()->addDays(max(1, (int) config('voters.privacy.request_due_days', 30)))]);
        if ($type === 'WITHDRAW' && $v) $this->privacy->setConsent($v, 'N', 'request', 'privacy request #' . $id, $this->user()->username());
        $this->audit()->log('PRIVACY_REQUEST', 'PRIVACY', $id, "$type for " . ($v?->epic_no ?? $name));
        return redirect('/admin/privacy')->with('success', 'privacy.requestSaved');
    }

    public function close(Request $r, int $id)
    {
        $st = $r->input('status') === 'REJECTED' ? 'REJECTED' : 'DONE';
        DB::table('privacy_requests')->where('id', $id)->update(['status' => $st, 'outcome' => mb_substr((string) $r->input('outcome'), 0, 1000) ?: null,
            'done_by' => $this->user()->username(), 'done_at' => now()]);
        $this->audit()->log('PRIVACY_REQUEST', 'PRIVACY', $id, $st);
        return redirect('/admin/privacy')->with('success', 'flash.updated');
    }

    /** Retention: erase the party-collected data of a whole assembly. The assembly number must be typed again. */
    public function retention(Request $r)
    {
        $a = (int) $r->input('assembly_no');
        if ($a <= 0 || (int) $r->input('confirm') !== $a || ($this->user()->scoped() && $a !== $this->user()->workAssembly())) return redirect('/admin/privacy')->with('error', 'privacy.eraseConfirmWrong');
        $n = $this->privacy->eraseAssembly($a, $this->user()->username());
        return redirect('/admin/privacy')->with('success', t('privacy.retentionDone', number_format($n), $a));
    }
}
