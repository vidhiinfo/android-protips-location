<?php

namespace App\Services\Messaging;

use App\Models\Campaign;
use App\Models\Election;
use App\Models\MessageTemplate;
use App\Models\OptOut;
use App\Models\OutboxMessage;
use App\Services\Auth\AuditService;
use App\Services\Voters\VoterFilter;
use App\Services\Voters\VoterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recipients, rendering and queueing. Rules: valid 10-digit mobile only, opt-outs skipped,
 * one message per number per campaign, nothing during the pre-poll silence period.
 */
class MessagingService
{
    public function __construct(private VoterService $voters, private AuditService $audit) {}

    public static function mask(?string $e164): string
    {
        return $e164 === null || strlen($e164) < 6 ? (string) $e164 : substr($e164, 0, -6) . 'xxxx' . substr($e164, -2);
    }

    public function e164(?string $mobile): ?string
    {
        $d = preg_replace('/[^0-9]/', '', (string) \App\Support\Text::asciiDigits((string) $mobile));
        if (strlen($d) === 12 && str_starts_with($d, '91')) $d = substr($d, 2);
        return preg_match('/^[6-9][0-9]{9}$/', $d) ? config('voters.messaging.country_code') . $d : null;
    }

    public function now(): Carbon { return Carbon::now(config('voters.messaging.time_zone')); }

    public function silencePeriod(): ?string
    {
        if (!config('voters.messaging.enforce_silence_period')) return null;
        $e = Election::where('current', true)->first();
        if (!$e || !$e->poll_date) return null;
        $today = $this->now()->startOfDay();
        $poll = Carbon::parse($e->poll_date->format('Y-m-d'), config('voters.messaging.time_zone'));
        return $today->gte($poll->copy()->subDays(2)) && $today->lte($poll) ? $e->name . ' (' . $poll->format('Y-m-d') . ')' : null;
    }

    public function insideSendWindow(): bool
    {
        $t = $this->now()->format('H:i');
        return $t >= config('voters.messaging.send_from') && $t < config('voters.messaging.send_until');
    }

    private function blocked(string $channel): array
    {
        return OptOut::where(fn ($q) => $q->whereNull('channel')->orWhere('channel', $channel))->pluck('mobile')->flip()->all();
    }

    private function recipients(VoterFilter $f, string $channel, array &$c): array
    {
        $blocked = $this->blocked($channel);
        $seen = []; $out = [];
        $f = clone $f;
        $f->set('deleted', 'exclude');   // never message voters deleted from the roll
        $c = ['voters' => 0, 'noMobile' => 0, 'optedOut' => 0, 'duplicates' => 0, 'noConsent' => 0];
        $consent = \App\Services\Privacy\PrivacyService::enabled();
        $onlyAgreed = $consent && config('voters.privacy.consent_required');
        foreach ($this->voters->searchLazy($f) as $v) {
            $c['voters']++;
            // consent (DPDP): never voters who refused or withdrew; with PRIVACY_CONSENT_REQUIRED=true only those who agreed
            if ($consent && ($v->consent_contact === 'N' || ($onlyAgreed && $v->consent_contact !== 'Y'))) { $c['noConsent']++; continue; }
            $e = $this->e164($v->mobile);
            if (!$e) { $c['noMobile']++; continue; }
            if (isset($blocked[substr($e, -10)])) { $c['optedOut']++; continue; }
            if (isset($seen[$e])) { $c['duplicates']++; continue; }
            $seen[$e] = true;
            $out[] = [$v, $e];
        }
        return $out;
    }

    public function preview(VoterFilter $f, MessageTemplate $t): array
    {
        $c = [];
        $r = $this->recipients($f, $t->channel, $c);
        $samples = []; $maxSeg = 1;
        foreach ($r as [$v, $e]) {
            $body = TemplateRenderer::render($t->body, $v);
            $maxSeg = max($maxSeg, TemplateRenderer::smsSegments($body));
            if (count($samples) < 5) $samples[] = [$v->name, self::mask($e), $body];
        }
        return $c + ['recipients' => count($r), 'samples' => $samples, 'maxSegments' => $maxSeg];
    }

    /** Freezes the recipients into the outbox; the cron command sends them. @throws \RuntimeException with a message key */
    public function create(?string $name, MessageTemplate $t, VoterFilter $f, ?string $summary, ?string $scheduledAt, string $user): Campaign
    {
        if (!$t->active) throw new \RuntimeException('msg.error.inactive');
        if ($this->silencePeriod()) throw new \RuntimeException('msg.error.silence');
        $c = [];
        $list = $this->recipients($f, $t->channel, $c);
        if (!$list) throw new \RuntimeException('msg.error.noRecipients');
        return DB::transaction(function () use ($name, $t, $f, $summary, $scheduledAt, $user, $list, $c) {
            $camp = Campaign::create(['name' => trim((string) $name) ?: $t->name, 'channel' => $t->channel, 'template_id' => $t->id,
                'filter_query' => $f->queryWithSort(), 'filter_summary' => $summary, 'status' => 'QUEUED',
                'scheduled_at' => $scheduledAt ? Carbon::parse($scheduledAt, config('voters.messaging.time_zone')) : null,
                'created_by' => $user, 'created_at' => now(), 'total' => count($list)]);
            $now = now();
            foreach (array_chunk($list, 500) as $chunk) {
                OutboxMessage::insert(array_map(fn ($x) => ['campaign_id' => $camp->id, 'voter_id' => $x[0]->id, 'voter_name' => $x[0]->name,
                    'mobile' => $x[1], 'body' => TemplateRenderer::render($t->body, $x[0]),
                    'param_values' => implode(TemplateRenderer::SEP, TemplateRenderer::params($t, $x[0])),
                    'status' => 'QUEUED', 'attempts' => 0, 'created_at' => $now], $chunk));
            }
            $this->audit->log('MSG_CAMPAIGN', 'CAMPAIGN', $camp->id, "{$t->channel} '{$camp->name}' to " . count($list)
                . " numbers ({$c['optedOut']} opted out, {$c['duplicates']} duplicate numbers, {$c['noMobile']} without mobile)");
            return $camp;
        });
    }

    public function cancel(Campaign $c, string $user): void
    {
        if (in_array($c->status, ['COMPLETED', 'CANCELLED'], true)) return;
        $n = OutboxMessage::where('campaign_id', $c->id)->where('status', 'QUEUED')->update(['status' => 'SKIPPED', 'error' => "Cancelled by $user"]);
        $c->update(['skipped' => $c->skipped + $n, 'status' => 'CANCELLED', 'completed_at' => now()]);
        $this->audit->log('MSG_CANCEL', 'CAMPAIGN', $c->id, "$n unsent messages cancelled");
    }

    public function optOut(?string $mobile, ?string $channel, ?string $reason, string $user): bool
    {
        $e = $this->e164($mobile);
        if (!$e) return false;
        $d = substr($e, -10);
        $exists = OptOut::where('mobile', $d)->where(fn ($q) => $channel ? $q->where('channel', $channel) : $q->whereNull('channel'))->exists();
        if (!$exists) {
            OptOut::create(['mobile' => $d, 'channel' => $channel ?: null, 'reason' => $reason, 'created_by' => $user, 'created_at' => now()]);
            $this->audit->log('OPT_OUT', 'OPTOUT', substr($d, 0, 2) . 'xxxxxx' . substr($d, 8), $channel ?: 'all channels');
        }
        return true;
    }

    public function isOptedOut(?string $mobile, string $channel): bool
    {
        $e = $this->e164($mobile);
        return $e !== null && isset($this->blocked($channel)[substr($e, -10)]);
    }
}
