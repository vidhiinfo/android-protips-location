<?php

namespace App\Console\Commands;

use App\Services\Privacy\PrivacyService;
use Illuminate\Console\Command;

/** Removes message texts and numbers older than MESSAGE_RETENTION_DAYS. Run daily (cron), e.g. right after vis:backup. */
class PrivacyClean extends Command
{
    protected $signature = 'vis:privacy-clean';
    protected $description = 'Remove old message texts and mobile numbers from the outbox (MESSAGE_RETENTION_DAYS)';

    public function handle(PrivacyService $p): int
    {
        $this->info($p->cleanMessages() . ' old messages cleaned');
        return self::SUCCESS;
    }
}
