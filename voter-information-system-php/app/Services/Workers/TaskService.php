<?php

namespace App\Services\Workers;

use App\Models\Voter;
use App\Models\Worker;
use App\Services\Messaging\Gateway;
use App\Services\Messaging\MessagingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks and targets for party workers, their progress, daily reminders, and attendance (check-in / check-out with GPS).
 */
class TaskService
{
    public const KINDS = ['SURVEY', 'VISIT', 'CALL', 'MEETING', 'OTHER'];

    public function __construct(private Gateway $gateway) {}

    public static function enabled(): bool { return Schema::hasTable('worker_tasks'); }

    /**
     * Progress of each task: SURVEY tasks count voters surveyed (political status or caste filled) since the task was given,
     * among the worker's own voters (or the booth's voters when the worker has none); other tasks use the count the worker reports.
     * Returns [task id => ['done' => n, 'pct' => 0-100 | null]].
     */
    public function progress(Collection $tasks): array
    {
        $out = [];
        foreach ($tasks as $t) {
            $done = (int) $t->done_count;
            if ($t->kind === 'SURVEY') {
                $q = Voter::where('assembly_no', $t->assembly_no)->where('deleted', false)
                    ->where(fn ($w) => $w->where(fn ($x) => $x->whereNotNull('political_status')->where('political_status', '<>', ''))
                        ->orWhere(fn ($x) => $x->whereNotNull('caste')->where('caste', '<>', '')));
                if ($t->created_at) $q->where('updated_at', '>=', $t->created_at);
                $own = $t->worker_id ? Voter::where('worker_id', $t->worker_id)->exists() : false;
                if ($own) $q->where('worker_id', $t->worker_id);
                elseif ($t->booth_no) $q->where('booth_no', $t->booth_no);
                else $q->whereRaw('1 = 0');
                $done = $q->count();
            }
            $out[$t->id] = ['done' => $done, 'pct' => $t->target ? min(100, (int) round(100 * $done / max(1, $t->target))) : ($t->status === 'DONE' ? 100 : null)];
        }
        return $out;
    }

    /** Open tasks of a login that are due today or overdue. */
    public function dueFor(?string $username): int
    {
        if (!$username || !self::enabled()) return 0;
        return DB::table('worker_tasks')->where('username', $username)->where('status', 'OPEN')->whereNotNull('due_date')->where('due_date', '<=', now()->toDateString())->count();
    }

    /** Mobile number to remind a task's worker on: the worker record, else the login's own mobile. */
    private function mobileOf(object $t): ?string
    {
        $m = $t->worker_id ? Worker::whereKey($t->worker_id)->value('mobile') : null;
        if (!$m && $t->username && Schema::hasColumn('app_users', 'mobile')) $m = DB::table('app_users')->where('username', $t->username)->value('mobile');
        return $m ?: null;
    }

    /**
     * Once a day (after TASK_REMINDER_FROM): one SMS / WhatsApp per worker listing their tasks due today or overdue.
     * Returns the number of reminders sent. Also runs from page visits when there is no cron job.
     */
    public function remind(bool $force = false): int
    {
        $channel = strtolower((string) config('voters.tasks.reminder_channel'));
        if (!in_array($channel, ['sms', 'whatsapp'], true) || !self::enabled() || !\App\Support\Permission::messagingOn()) return 0;
        $tz = config('voters.messaging.time_zone', 'Asia/Kolkata');
        $now = now($tz);
        if (!$force && $now->format('H:i') < (string) config('voters.tasks.reminder_from', '08:00')) return 0;
        $today = $now->toDateString();
        if (!$force && Cache::get('vis.task.remind.' . $today)) return 0;
        Cache::put('vis.task.remind.' . $today, true, 86400 * 2);
        $tasks = DB::table('worker_tasks')->where('status', 'OPEN')->whereNotNull('due_date')->where('due_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('reminded_on')->orWhere('reminded_on', '<', $today))->orderBy('due_date')->get();
        $sent = 0;
        $msg = app(MessagingService::class);
        foreach ($tasks->groupBy(fn ($t) => $t->worker_id ? 'w' . $t->worker_id : 'u' . $t->username) as $group) {
            $e164 = $msg->e164($this->mobileOf($group->first()));
            if (!$e164) continue;
            $lines = $group->take(5)->map(fn ($t) => '- ' . $t->title . ' (' . ($t->due_date < $today ? t('task.overdue') : t('task.dueToday')) . ')')->implode("\n");
            $body = t('task.reminderText', $group->count()) . "\n" . $lines;
            $res = $this->gateway->send($channel === 'whatsapp' ? 'WHATSAPP' : 'SMS', $e164, $body, null, ['count' => $group->count()]);
            if ($res->success) {
                $sent++;
                DB::table('worker_tasks')->whereIn('id', $group->pluck('id'))->update(['reminded_on' => $today]);
            } else {
                Log::warning('Task reminder not sent: ' . $res->error);
            }
        }
        return $sent;
    }

    // ---------------------------------------------------------------- attendance

    public function today(string $username): ?object
    {
        return DB::table('attendance')->where('username', $username)->where('day', now()->toDateString())->first();
    }

    private static function coord($v, float $min, float $max): ?float
    {
        return is_numeric($v) && (float) $v >= $min && (float) $v <= $max ? round((float) $v, 7) : null;
    }

    /** Check in (first time today) or check out (later). Returns 'in', 'out' or 'already'. */
    public function punch(string $username, ?int $assembly, $lat, $lng, ?string $note): string
    {
        $lat = self::coord($lat, 6, 38); $lng = self::coord($lng, 68, 98);
        if ($lat === null || $lng === null) $lat = $lng = null;
        $row = $this->today($username);
        if (!$row) {
            DB::table('attendance')->insert(['username' => $username, 'worker_id' => Worker::forUsername($username)?->id, 'assembly_no' => $assembly,
                'day' => now()->toDateString(), 'check_in' => now(), 'in_lat' => $lat, 'in_lng' => $lng, 'note' => $note ? mb_substr($note, 0, 300) : null]);
            return 'in';
        }
        if ($row->check_out) return 'already';
        DB::table('attendance')->where('id', $row->id)->update(['check_out' => now(), 'out_lat' => $lat, 'out_lng' => $lng,
            'note' => $note ? mb_substr(trim(($row->note ? $row->note . ' | ' : '') . $note), 0, 300) : $row->note]);
        return 'out';
    }
}
