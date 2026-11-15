<?php

namespace App\Services\Messaging;

use App\Models\Campaign;
use App\Models\OutboxMessage;

/**
 * Sends queued messages. Called every minute by cron (php artisan messages:send), it works for up to
 * MSG_RUN_SECONDS at MSG_RATE messages per second, only inside the send window and never in the silence period.
 */
class OutboxProcessor
{
    public function __construct(private Gateway $gateway, private MessagingService $service) {}

    public function recoverInterrupted(): int
    {
        // a message left in SENDING may or may not have gone out: mark failed rather than risk a duplicate
        // (only called while holding the send lock, so no other run is sending right now)
        return OutboxMessage::where('status', 'SENDING')->update(['status' => 'FAILED', 'error' => 'Interrupted (sending stopped part-way)']);
    }

    public function run(bool $ignoreWindow = false, ?int $maxSeconds = null): int
    {
        if (!$ignoreWindow && !$this->service->insideSendWindow()) return 0;
        if ($this->service->silencePeriod()) return 0;
        // one run at a time: cron starts a run every minute, an earlier slow run may still be busy
        $lock = \Illuminate\Support\Facades\Cache::lock('vis-messages-send', 300);
        if (!$lock->get()) return 0;
        try {
            return $this->runLocked($maxSeconds);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(?int $maxSeconds): int
    {
        $this->recoverInterrupted();
        $rate = max(1, (int) config('voters.messaging.rate_per_second'));
        $deadline = microtime(true) + ($maxSeconds ?? (int) config('voters.messaging.run_seconds'));
        $done = 0;
        $active = Campaign::with('template')->whereIn('status', ['QUEUED', 'SENDING'])->orderBy('id')->get();
        foreach ($active as $c) {
            if ($c->scheduled_at && $c->scheduled_at->gt($this->service->now())) continue;
            while (microtime(true) < $deadline) {
                $batch = OutboxMessage::where('campaign_id', $c->id)->where('status', 'QUEUED')->orderBy('id')->limit($rate * 3)->get();
                if ($batch->isEmpty()) break;
                if ($c->status === 'QUEUED') $c->update(['status' => 'SENDING']);
                foreach ($batch as $m) {
                    $this->sendOne($c, $m);
                    $done++;
                    usleep((int) (1000000 / $rate));
                    if (microtime(true) >= $deadline) break;
                }
            }
            $this->refresh($c);
            if (microtime(true) >= $deadline) break;
        }
        return $done;
    }

    private function sendOne(Campaign $c, OutboxMessage $m): void
    {
        $m->update(['status' => 'SENDING', 'attempts' => $m->attempts + 1]);
        $params = $m->param_values === null || $m->param_values === '' ? [] : explode(TemplateRenderer::SEP, $m->param_values);
        $r = $this->gateway->send($c->channel, $m->mobile, $m->body, $c->template, $params);
        if ($r->success) $m->update(['status' => 'SENT', 'provider_id' => $r->providerId, 'error' => null, 'sent_at' => now()]);
        elseif ($r->retryable && $m->attempts < (int) config('voters.messaging.max_attempts')) $m->update(['status' => 'QUEUED', 'error' => mb_substr($r->error, 0, 300)]);
        else $m->update(['status' => 'FAILED', 'error' => mb_substr((string) $r->error, 0, 300)]);
    }

    public function refresh(Campaign $c): void
    {
        $counts = OutboxMessage::where('campaign_id', $c->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $c->sent = (int) ($counts['SENT'] ?? 0);
        $c->failed = (int) ($counts['FAILED'] ?? 0);
        $c->skipped = (int) ($counts['SKIPPED'] ?? 0);
        if (($counts['QUEUED'] ?? 0) + ($counts['SENDING'] ?? 0) == 0 && $c->status !== 'CANCELLED') { $c->status = 'COMPLETED'; $c->completed_at = now(); }
        $c->save();
    }
}
