<?php

namespace App\Console\Commands;

use App\Services\State\StateSummaryService;
use Illuminate\Console\Command;

/**
 * State server: fetch the totals of every division (STATE_SOURCES). cPanel / crontab, every 5 minutes:
 *   php /path/to/state-site/artisan vis:state-pull >> /dev/null 2>&1
 */
class StatePull extends Command
{
    protected $signature = 'vis:state-pull';
    protected $description = 'Fetch the state summary feeds of all division installations';

    public function handle(StateSummaryService $s): int
    {
        if (!StateSummaryService::enabled()) { $this->warn('STATE_SOURCES is not set'); return self::SUCCESS; }
        foreach ($s->pull() as $name => $ok) $this->line(($ok ? 'OK      ' : 'OFFLINE ') . $name);
        return self::SUCCESS;
    }
}
