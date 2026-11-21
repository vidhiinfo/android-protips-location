<?php

namespace App\Console\Commands;

use App\Services\Workers\TaskService;
use Illuminate\Console\Command;

/** Sends today's task reminders to workers (TASK_REMINDER_CHANNEL). Run once a morning from cron; page visits also trigger it. */
class TaskReminders extends Command
{
    protected $signature = 'vis:task-reminders {--force : send even if already sent today / before TASK_REMINDER_FROM}';
    protected $description = 'Remind workers (SMS / WhatsApp) of tasks due today or overdue';

    public function handle(TaskService $t): int
    {
        $this->info($t->remind((bool) $this->option('force')) . ' reminders sent');
        return self::SUCCESS;
    }
}
