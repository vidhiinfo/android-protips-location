<?php

namespace App\Services\Messaging;

use App\Models\MessageTemplate;
use App\Models\Voter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic birthday / anniversary greetings: once a day, after the rule's time, a campaign is queued for the voters
 * whose date of birth / anniversary is today (Feb 29 is greeted on Feb 28 in other years). Same rules as any campaign:
 * consent, opt-outs, one message per number, nothing in the pre-poll silence period. The normal sender (cron) delivers it.
 */
class GreetingService
{
    public const KINDS = ['BIRTHDAY' => 'dob', 'ANNIVERSARY' => 'anniversary'];

    public function __construct(private MessagingService $msg) {}

    public static function enabled(): bool { return Schema::hasTable('auto_greetings'); }

    /** Voters with the occasion on a day (not deleted from the roll). */
    public function voters(string $kind, ?int $assembly, Carbon $day)
    {
        $col = self::KINDS[$kind];
        $q = Voter::withoutGlobalScopes()->where('deleted', false)->whereNotNull($col)
            ->when($assembly, fn ($x) => $x->where('assembly_no', $assembly));
        $leapCatchUp = $day->month === 2 && $day->day === 28 && !$day->isLeapYear();
        return $q->whereMonth($col, $day->month)->where(function ($x) use ($col, $day, $leapCatchUp) {
            $x->whereDay($col, $day->day);
            if ($leapCatchUp) $x->orWhereDay($col, 29);
        })->orderBy('assembly_no')->orderBy('booth_no')->orderBy('serial_no');
    }

    /** How many voters have the occasion on each of the next $days days: [Y-m-d => n]. */
    public function upcoming(string $kind, ?int $assembly, int $days = 7): array
    {
        $out = [];
        $d = $this->msg->now()->startOfDay();
        for ($i = 0; $i < $days; $i++, $d->addDay()) $out[$d->toDateString()] = $this->voters($kind, $assembly, $d->copy())->count();
        return $out;
    }

    /**
     * Queues today's greetings for every active rule that has not run today and whose time has come.
     * Returns [rule id => campaign id | reason]. Runs from cron (vis:greetings) and from page visits.
     */
    public function run(bool $force = false): array
    {
        if (!self::enabled() || !\App\Support\Permission::messagingOn()) return [];
        $now = $this->msg->now();
        $today = $now->toDateString();
        if (!$force) {
            if (Cache::get('vis.greet.checked')) return [];
            Cache::put('vis.greet.checked', true, 600);
        }
        $out = [];
        foreach (DB::table('auto_greetings')->where('active', true)->where(fn ($q) => $q->whereNull('last_run_on')->orWhere('last_run_on', '<', $today))->get() as $rule) {
            if (!$force && $now->format('H:i') < $rule->send_at) continue;
            if ($this->msg->silencePeriod()) { $out[$rule->id] = 'silence'; continue; }   // tried again tomorrow
            $t = MessageTemplate::find($rule->template_id);
            $campaign = null;
            if ($t && $t->active) {
                $list = $this->msg->eligible($this->voters($rule->kind, $rule->assembly_no, $now->copy()->startOfDay())->cursor(), $t->channel, $c);
                if ($list) {
                    try {
                        $campaign = $this->msg->queue(t('greet.campaignName.' . $rule->kind, $today), $t, $list, $c, null,
                            t('greet.kind.' . $rule->kind) . ($rule->assembly_no ? ' · ' . t('col.assemblyNo') . ' ' . $rule->assembly_no : ''), null, 'auto-greetings')->id;
                    } catch (\Throwable $e) { Log::error('Greetings not queued: ' . $e->getMessage()); continue; }
                }
            }
            DB::table('auto_greetings')->where('id', $rule->id)->update(['last_run_on' => $today, 'last_campaign_id' => $campaign]);
            $out[$rule->id] = $campaign ?? ($t && $t->active ? 'nobody' : 'template');
        }
        return $out;
    }
}
