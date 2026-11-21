<?php

namespace App\Services\Privacy;

use App\Models\Voter;
use App\Services\Auth\AuditService;
use App\Services\Voters\RollUpdateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consent and privacy tools (DPDP Act 2023):
 *  - consent to be contacted per voter, with a history of every change;
 *  - "everything we hold about this voter" (access request);
 *  - erase on request: the data the party collected is removed; the roll details (published by the Election Commission) stay;
 *  - retention: erase the party-collected data of a whole assembly after the election, and old message texts.
 */
class PrivacyService
{
    public const SOURCES = ['survey', 'phone', 'form', 'request', 'other'];
    public const TYPES = ['ACCESS', 'CORRECTION', 'ERASURE', 'WITHDRAW', 'OTHER'];

    /** The data the party collected (never the roll): erased on request. */
    public static function partyFields(): array
    {
        return array_values(array_unique(array_merge(RollUpdateService::SURVEY_FIELDS, ['category', 'shift1', 'shift2'])));
    }

    public function __construct(private AuditService $audit) {}

    public static function enabled(): bool { return Schema::hasColumn('voters', 'consent_contact'); }

    public function setConsent(Voter $v, ?string $value, string $source, ?string $note, ?string $by): void
    {
        $value = in_array($value, ['Y', 'N'], true) ? $value : null;
        $source = in_array($source, self::SOURCES, true) ? $source : 'other';
        DB::table('voters')->where('id', $v->id)->update(['consent_contact' => $value, 'consent_at' => now(), 'consent_by' => $by, 'consent_source' => $source, 'updated_at' => now()]);
        DB::table('consent_log')->insert(['voter_id' => $v->id, 'value' => $value, 'source' => $source, 'note' => $note ? mb_substr($note, 0, 300) : null, 'by_user' => $by, 'at' => now()]);
        $this->audit->log('CONSENT', 'VOTER', $v->id, ($value ?? 'not asked') . " ($source)" . ($note ? ": $note" : ''));
    }

    /** Everything the system holds about one voter, for an access request. */
    public function export(Voter $v): array
    {
        $row = (array) DB::table('voters')->where('id', $v->id)->first();
        unset($row['house_key']);
        $out = ['generatedAt' => now()->toIso8601String(), 'voter' => $row,
            'consentHistory' => DB::table('consent_log')->where('voter_id', $v->id)->orderBy('at')->get(['value', 'source', 'note', 'by_user', 'at'])->all()];
        if (Schema::hasTable('grievances')) $out['grievances'] = DB::table('grievances')->where('voter_id', $v->id)->get(['id', 'category', 'subject', 'description', 'status', 'created_at', 'resolved_at'])->all();
        if (Schema::hasTable('poll_marks')) $out['pollDayMarks'] = DB::table('poll_marks as p')->join('elections as e', 'e.id', '=', 'p.election_id')->where('p.voter_id', $v->id)->get(['e.name as election', 'p.marked_at'])->all();
        $out['messages'] = DB::table('message_outbox')->where('voter_id', $v->id)->orderBy('created_at')->get(['mobile', 'body', 'status', 'created_at', 'sent_at'])->all();
        $out['privacyRequests'] = DB::table('privacy_requests')->where('voter_id', $v->id)->get(['type', 'status', 'requested_at', 'done_at', 'outcome'])->all();
        foreach (['call_logs', 'transport_requests'] as $tbl) if (Schema::hasTable($tbl)) $out[$tbl] = DB::table($tbl)->where('voter_id', $v->id)->get()->all();
        $this->audit->log('PRIVACY_EXPORT', 'VOTER', $v->id, 'access request');
        return $out;
    }

    /** Erase on request: party-collected data and message history removed, consent set to "refused"; the roll details stay. */
    public function erase(Voter $v, ?string $by, string $why = 'request'): void
    {
        DB::transaction(function () use ($v, $by, $why) {
            DB::table('voters')->where('id', $v->id)->update(array_fill_keys(self::partyFields(), null)
                + ['consent_contact' => 'N', 'consent_at' => now(), 'consent_by' => $by, 'consent_source' => 'request', 'erased_at' => now(), 'updated_at' => now()]);
            DB::table('message_outbox')->where('voter_id', $v->id)->update(['mobile' => '', 'body' => '[erased]', 'param_values' => null]);
            if (Schema::hasTable('grievances')) DB::table('grievances')->where('voter_id', $v->id)->update(['mobile' => null]);
            foreach (['call_logs' => ['note' => null], 'house_locations' => null] as $tbl => $upd) {
                if (!Schema::hasTable($tbl) || $upd === null) continue;
                DB::table($tbl)->where('voter_id', $v->id)->update($upd);
            }
            DB::table('consent_log')->insert(['voter_id' => $v->id, 'value' => 'N', 'source' => 'request', 'note' => 'data erased (' . $why . ')', 'by_user' => $by, 'at' => now()]);
        });
        $this->audit->log('PRIVACY_ERASE', 'VOTER', $v->id, "party data erased ($why)");
    }

    /** Retention: erases the party-collected data of every voter of an assembly (e.g. some months after the election). */
    public function eraseAssembly(int $assembly, ?string $by): int
    {
        $n = 0;
        DB::table('voters')->where('assembly_no', $assembly)->whereNull('erased_at')->orderBy('id')->select('id')->chunkById(2000, function ($ids) use (&$n, $by) {
            $list = $ids->pluck('id')->all();
            $n += DB::table('voters')->whereIn('id', $list)->update(array_fill_keys(self::partyFields(), null) + ['erased_at' => now(), 'updated_at' => now()]);
            DB::table('message_outbox')->whereIn('voter_id', $list)->update(['mobile' => '', 'body' => '[erased]', 'param_values' => null]);
        });
        $this->audit->log('PRIVACY_RETENTION', 'ASSEMBLY', $assembly, "party data of $n voters erased", $by);
        return $n;
    }

    /** Message texts and mobile numbers older than MESSAGE_RETENTION_DAYS (365) are removed from the outbox. */
    public function cleanMessages(): int
    {
        $days = (int) config('voters.privacy.message_retention_days', 365);
        if ($days <= 0) return 0;
        return DB::table('message_outbox')->where('created_at', '<', now()->subDays($days))->where('body', '<>', '[erased]')
            ->update(['mobile' => '', 'body' => '[erased]', 'param_values' => null]);
    }
}
