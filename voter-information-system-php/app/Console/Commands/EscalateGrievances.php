<?php

namespace App\Console\Commands;

use App\Services\Grievances\GrievanceService;
use Illuminate\Console\Command;

/** Escalates overdue grievances (Grievances > Deadlines). Run from cron every hour; page visits also trigger it. */
class EscalateGrievances extends Command
{
    protected $signature = 'vis:grievance-escalate';
    protected $description = 'Escalate open grievances that are past their due date';

    public function handle(GrievanceService $g): int
    {
        $this->info($g->escalate(true) . ' grievances escalated');
        return self::SUCCESS;
    }
}
